angular.module('posterModule').directive('posterTool', [
    '$timeout', '$http', function ($timeout, $http) {
        'use strict';

        return {
            restrict: 'E',
            replace: true,
            scope: {
                slide: '=',
                close: '&',
                tool: '='
            },
            link: function (scope) {
                // Search by name as default.
                scope.typeSelect = 'searchName';
                scope.searchName = '';
                scope.searchUrl = '';

                scope.pagerBack = function () {
                  scope.pager.centerItem = Math.max(scope.pager.centerItem - 10, 1);
                };

                scope.pagerForward = function () {
                    scope.pager.centerItem = Math.min(scope.pager.centerItem + 10, scope.pager.pagerMax);
                };

                scope.getPagerPages = function () {
                    scope.pager.pages = [];

                    var center10Page = parseInt(scope.pager.centerItem / 10);

                    for (var i = 1; i <= 10; i++) {
                        if (i + center10Page * 10 <= scope.pager.pagerMax) {
                            scope.pager.pages.push(i + center10Page * 10);
                        }
                    }

                    return scope.pager.pages;
                };

                scope.calculatePager = function(meta) {
                    if (!meta) {
                        return;
                    }

                    scope.pager = {
                      pages: [],
                      currentPage: meta.page,
                      pagerMax: meta.number_of_pages,
                      itemsPerPage: meta.items_per_page,
                      totalResults: meta.total_results,
                      centerItem: meta.page
                    };
                };

                scope.search = function (page) {
                    scope.displayEvent = null;
                    scope.events = null;

                    var params = {};

                    if (page) {
                        params.page = page;
                    }

                    if (scope.typeSelect === 'searchName') {
                        params.name = scope.searchName;
                    }
                    else if (scope.typeSelect === 'searchUrl') {
                        params.url = scope.searchUrl;
                    }

                    $http.get('/api/os2display_poster/events', {
                        params: params
                    }).then(
                        function success(response) {
                            $timeout(function () {
                                scope.events = response.data.events;
                                scope.meta = response.data.meta;

                                scope.calculatePager(scope.meta);
                            });
                        }
                    );
                };

                scope.clickEvent = function (event) {
                    scope.displayEvent = event;

                    // If only one occurrence, select that.
                    if (scope.displayEvent.occurrences.length === 1) {
                        scope.clickOccurrence(scope.displayEvent.occurrences[0]);
                    }
                };

                scope.refreshEvent = function () {
                    $http.get('/api/os2display_poster/occurrence', {
                        params: {
                            occurrenceId: scope.slide.options.data.occurrenceId
                        }
                    }).then(
                        function success(response) {
                            $timeout(function () {
                                scope.slide.options.data = response.data;
                            });
                        }
                    );
                };

                scope.clickOccurrence = function (occurrence) {
                    $http.get('/api/os2display_poster/occurrence', {
                        params: {
                            occurrenceId: occurrence['@id']
                        }
                    }).then(
                        function success(response) {
                            $timeout(function () {
                                scope.slide.options.data = response.data;
                            });
                        }
                    );

                    scope.close();
                };
            },
            templateUrl: '/bundles/os2displayposter/apps/posterModule/posterTool.html'
        };
    }
]);
