/**
 * Copyright © Tony. All rights reserved.
 *
 * Currency converter UI component: pair selection, swap, amount conversion,
 * current rate lookup and historical chart (Chart.js shipped with Magento's lib/web).
 */
define([
    'jquery',
    'ko',
    'uiComponent',
    'mage/translate',
    'chartjs/Chart.min'
], function ($, ko, Component, $t, Chart) {
    'use strict';

    var LOCALE = document.documentElement.lang || undefined;

    /**
     * Format a rate/amount so both large (JPY per BTC) and tiny (BTC per JPY) values stay readable.
     *
     * @param {Number} value
     * @param {Number} maxFraction
     *
     * @returns {String}
     */
    var formatNumber = function (value, maxFraction) {
        if (value === null || value === undefined || isNaN(value)) {
            return '';
        }

        if (value !== 0 && Math.abs(value) < 1) {
            return new Intl.NumberFormat(LOCALE, {maximumSignificantDigits: 6}).format(value);
        }

        return new Intl.NumberFormat(LOCALE, {maximumFractionDigits: maxFraction}).format(value);
    };

    /**
     * Short x-axis label: "Aug 19" for ranges up to 6M, "Nov 21" (month + year) for 1Y/5Y.
     * Tooltips keep the full Y-m-d date.
     *
     * @param {String} isoDate Y-m-d
     * @param {String} range
     *
     * @returns {String}
     */
    var formatDateTick = function (isoDate, range) {
        var parts = String(isoDate).split('-'),
            date,
            long = range === '1Y' || range === '5Y';

        if (parts.length !== 3) {
            return isoDate;
        }
        date = new Date(Date.UTC(+parts[0], +parts[1] - 1, +parts[2]));

        return new Intl.DateTimeFormat(LOCALE, long ?
            {month: 'short', year: '2-digit', timeZone: 'UTC'} :
            {month: 'short', day: 'numeric', timeZone: 'UTC'}
        ).format(date);
    };

    /**
     * Number of x-axis labels that fit the chart width (~90px per label, 3–8 labels).
     *
     * @param {Number} width
     *
     * @returns {Number}
     */
    var xTickLimit = function (width) {
        return Math.max(3, Math.min(8, Math.floor((width || 0) / 90)));
    };

    return Component.extend({
        defaults: {
            template: 'Tony_CurrencyConverter/converter',
            currencyGroups: [],
            defaultFrom: '',
            defaultTo: '',
            ranges: [],
            defaultRange: '3M',
            rateUrl: '',
            historyUrl: '',
            unavailable: false
        },

        /**
         * Initialize observables, subscriptions and the first data load.
         *
         * @returns {Object}
         */
        initialize: function () {
            var params = new URLSearchParams(window.location.search),
                amount = parseFloat(params.get('amount'));

            this._super();

            this.chart = null;
            this.canvas = null;
            this.refreshTimer = null;
            this.rateRequestId = 0;
            this.historyRequestId = 0;
            this.currencyIndex = {};

            this.currencyGroups.forEach(function (group) {
                group.items.forEach(function (item) {
                    this.currencyIndex[item.code] = item;
                }, this);
            }, this);

            this.from = ko.observable(this.pickCode(params.get('from'), this.defaultFrom));
            this.to = ko.observable(this.pickCode(params.get('to'), this.defaultTo));
            this.range = ko.observable(
                this.ranges.indexOf(params.get('range')) !== -1 ? params.get('range') : this.defaultRange
            );
            this.amount = ko.observable(amount > 0 ? String(amount) : '1');

            this.rate = ko.observable(null);
            this.rateDate = ko.observable('');
            this.rateLoading = ko.observable(false);
            this.rateError = ko.observable('');

            this.points = ko.observableArray([]);
            this.historyLoading = ko.observable(false);
            this.historyError = ko.observable('');

            this.swapLabel = $t('Swap currencies');
            this.rangeGroupLabel = $t('Chart range');
            this.initComputed();

            this.from.subscribe(this.scheduleRefresh, this);
            this.to.subscribe(this.scheduleRefresh, this);
            this.range.subscribe(function () {
                this.loadHistory();
                this.updateUrl();
            }, this);
            this.amount.subscribe(this.updateUrl, this);

            if (!this.unavailable) {
                this.refresh();
            }

            return this;
        },

        /**
         * Derived, display-ready values.
         */
        initComputed: function () {
            this.sameCurrency = ko.pureComputed(function () {
                return this.from() === this.to();
            }, this);

            this.hasRate = ko.pureComputed(function () {
                return this.rate() !== null && !this.rateLoading() && !this.rateError();
            }, this);

            this.amountValue = ko.pureComputed(function () {
                var value = parseFloat(this.amount());

                return isNaN(value) || value < 0 ? 0 : value;
            }, this);

            this.amountDisplay = ko.pureComputed(function () {
                return formatNumber(this.amountValue(), 2);
            }, this);

            this.convertedDisplay = ko.pureComputed(function () {
                return this.rate() === null ? '' : formatNumber(this.amountValue() * this.rate(), 2);
            }, this);

            this.rateLine = ko.pureComputed(function () {
                return this.rate() === null ? '' :
                    '1 ' + this.from() + ' = ' + formatNumber(this.rate(), 4) + ' ' + this.to();
            }, this);

            this.inverseRateLine = ko.pureComputed(function () {
                return !this.rate() ? '' :
                    '1 ' + this.to() + ' = ' + formatNumber(1 / this.rate(), 4) + ' ' + this.from();
            }, this);

            this.rateDateLine = ko.pureComputed(function () {
                return this.rateDate() ? $t('Reference rate as of %1').replace('%1', this.rateDate()) : '';
            }, this);

            this.historyTitle = ko.pureComputed(function () {
                return $t('%1 to %2 history').replace('%1', this.from()).replace('%2', this.to());
            }, this);

            this.change = ko.pureComputed(function () {
                var points = this.points(),
                    first,
                    last;

                if (points.length < 2) {
                    return null;
                }
                first = points[0].rate;
                last = points[points.length - 1].rate;

                return first ? (last - first) / first * 100 : null;
            }, this);

            this.changeDisplay = ko.pureComputed(function () {
                var change = this.change();

                if (change === null) {
                    return '';
                }

                return (change > 0 ? '+' : '') + change.toFixed(2) + '% ' +
                    $t('over %1').replace('%1', this.range());
            }, this);

            this.changeClass = ko.pureComputed(function () {
                var change = this.change();

                if (change === null || Math.abs(change) < 0.005) {
                    return 'is-flat';
                }

                return change > 0 ? 'is-up' : 'is-down';
            }, this);

            this.historyEmpty = ko.pureComputed(function () {
                return !this.historyLoading() && !this.historyError() && !this.sameCurrency() &&
                    this.points().length === 0;
            }, this);
        },

        /**
         * Validate a code from the URL against the available list.
         *
         * @param {String|null} candidate
         * @param {String} fallback
         *
         * @returns {String}
         */
        pickCode: function (candidate, fallback) {
            candidate = (candidate || '').toUpperCase();

            return this.currencyIndex[candidate] ? candidate : fallback;
        },

        /**
         * Swap "from" and "to"; the subscriptions coalesce into a single refresh.
         */
        swap: function () {
            var from = this.from();

            this.from(this.to());
            this.to(from);
        },

        /**
         * Select a chart range (1M, 3M, 6M, 1Y or 5Y).
         *
         * @param {String} range
         */
        setRange: function (range) {
            this.range(range);
        },

        /**
         * Coalesce multiple observable changes (e.g. swap) into one refresh.
         */
        scheduleRefresh: function () {
            clearTimeout(this.refreshTimer);
            this.refreshTimer = setTimeout(this.refresh.bind(this), 0);
        },

        /**
         * Reload rate and history for the current pair.
         */
        refresh: function () {
            this.updateUrl();
            this.loadRate();
            this.loadHistory();
        },

        /**
         * Fetch the current rate. Stale responses (after a newer request) are ignored.
         */
        loadRate: function () {
            var requestId = ++this.rateRequestId;

            this.rateError('');

            if (this.sameCurrency()) {
                this.rate(1);
                this.rateDate('');
                this.rateLoading(false);

                return;
            }

            this.rateLoading(true);
            $.ajax({
                url: this.rateUrl,
                data: {from: this.from(), to: this.to()},
                dataType: 'json'
            }).done(function (response) {
                if (requestId !== this.rateRequestId) {
                    return;
                }
                this.rate(Number(response.rate));
                this.rateDate(response.date);
            }.bind(this)).fail(function (xhr) {
                if (requestId !== this.rateRequestId) {
                    return;
                }
                this.rate(null);
                this.rateError(this.errorMessage(xhr, $t('Could not load the exchange rate.')));
            }.bind(this)).always(function () {
                if (requestId === this.rateRequestId) {
                    this.rateLoading(false);
                }
            }.bind(this));
        },

        /**
         * Fetch the rate history for the selected range.
         */
        loadHistory: function () {
            var requestId = ++this.historyRequestId;

            this.historyError('');

            if (this.sameCurrency()) {
                this.points([]);
                this.historyLoading(false);
                this.renderChart();

                return;
            }

            this.historyLoading(true);
            $.ajax({
                url: this.historyUrl,
                data: {from: this.from(), to: this.to(), range: this.range()},
                dataType: 'json'
            }).done(function (response) {
                if (requestId !== this.historyRequestId) {
                    return;
                }
                this.points(response.points || []);
            }.bind(this)).fail(function (xhr) {
                if (requestId !== this.historyRequestId) {
                    return;
                }
                this.points([]);
                this.historyError(this.errorMessage(xhr, $t('Could not load the rate history.')));
            }.bind(this)).always(function () {
                if (requestId === this.historyRequestId) {
                    this.historyLoading(false);
                    this.renderChart();
                }
            }.bind(this));
        },

        /**
         * Message from a failed AJAX response, or the fallback text.
         *
         * @param {Object} xhr
         * @param {String} fallback
         *
         * @returns {String}
         */
        errorMessage: function (xhr, fallback) {
            return xhr && xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : fallback;
        },

        /**
         * Keep the URL shareable: ?from=USD&to=EUR&range=3M&amount=1
         */
        updateUrl: function () {
            var params = new URLSearchParams(window.location.search);

            if (!window.history || !window.history.replaceState) {
                return;
            }
            params.set('from', this.from());
            params.set('to', this.to());
            params.set('range', this.range());
            params.set('amount', String(this.amountValue()));
            window.history.replaceState(null, '', window.location.pathname + '?' + params.toString());
        },

        /**
         * Hook called by the afterRender binding on the chart <canvas>.
         *
         * @param {HTMLCanvasElement} canvas
         */
        initChart: function (canvas) {
            this.canvas = canvas;
            this.renderChart();
        },

        /**
         * Create or update the line chart from the current points.
         */
        renderChart: function () {
            var points = this.points(),
                labels = points.map(function (p) {
                    return p.date;
                }),
                data = points.map(function (p) {
                    return p.rate;
                }),
                label = this.from() + ' → ' + this.to(),
                self = this,
                ctx,
                gradient;

            if (!this.canvas) {
                return;
            }

            if (this.chart) {
                this.chart.data.labels = labels;
                this.chart.data.datasets[0].data = data;
                this.chart.data.datasets[0].label = label;
                this.chart.update();

                return;
            }

            ctx = this.canvas.getContext('2d');
            gradient = ctx.createLinearGradient(0, 0, 0, this.canvas.clientHeight || 260);
            gradient.addColorStop(0, 'rgba(25, 121, 195, 0.25)');
            gradient.addColorStop(1, 'rgba(25, 121, 195, 0)');

            this.chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: label,
                        data: data,
                        borderColor: '#1979c3',
                        backgroundColor: gradient,
                        borderWidth: 2,
                        fill: true,
                        tension: 0.2,
                        pointRadius: 0,
                        pointHoverRadius: 4,
                        pointHitRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: {duration: 300},
                    onResize: function (chart, size) {
                        chart.options.scales.x.ticks.maxTicksLimit = xTickLimit(size.width);
                    },
                    interaction: {mode: 'index', intersect: false},
                    plugins: {
                        legend: {display: false},
                        tooltip: {
                            callbacks: {
                                label: function (item) {
                                    return ' ' + formatNumber(item.parsed.y, 6);
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: {display: false},
                            ticks: {
                                maxTicksLimit: xTickLimit(this.canvas.parentNode.clientWidth),
                                maxRotation: 0,
                                autoSkip: true,
                                autoSkipPadding: 16,
                                callback: function (value) {
                                    return formatDateTick(this.getLabelForValue(value), self.range());
                                }
                            }
                        },
                        y: {
                            ticks: {
                                callback: function (value) {
                                    return formatNumber(value, 4);
                                }
                            }
                        }
                    }
                }
            });
        }
    });
});
