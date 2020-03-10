/**
 * Poster tool. Select between subscription or single.
 */
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
            link: function (scope, el) {
                scope.selectedOption = null;
                scope.selectOptions = [];

                scope.searchText = '';

                /**
                 * Select type of slide.
                 *
                 * @param type
                 *   'single' or 'subscription'
                 */
                scope.selectType = function (type) {
                    scope.slide.options.type = type;

                    if (type === 'subscription') {
                        scope.loading = true;
                    }
                };

                scope.selectSubOption = function (subOption) {
                    console.log(subOption);
                };

                scope.submitSearch = function (search)
                {
                    if (search.length < 3) {
                        console.log('Minimum search length 3');
                        return;
                    }

                    scope.loading = true;

                    scope.selectOptions = [
                        {
                            text: 'Places',
                            id: 'place',
                            subOptions: []
                        },
                        {
                            text: 'Organizers',
                            id: 'organizer',
                            subOptions: []
                        },
                        {
                            text: 'Tags',
                            id: 'tag',
                            subOptions: []
                        }
                    ];

                    $http.get('/api/os2display_poster/option', {params: {search: search}}).then(function (resp) {
                        var data = resp.data;

                        for (var i = 0; i < data.places.length; i++) {
                            var place = data.places[i];
                            scope.selectOptions[0].subOptions.push({
                                id: place['id'],
                                '@id': place['@id'],
                                text: place['name'],
                                type: 'place'
                            });
                        }

                        for (var i = 0; i < data.organizers.length; i++) {
                            var organizer = data.organizers[i];
                            scope.selectOptions[1].subOptions.push({
                                id: organizer['id'],
                                '@id': organizer['@id'],
                                text: organizer['name'],
                                type: 'organizer'
                            });
                        }

                        for (var i = 0; i < data.tags.length; i++) {
                            var tag = data.tags[i];
                            scope.selectOptions[2].subOptions.push({
                                id: tag['id'],
                                '@id': tag['@id'],
                                text: tag['name'],
                                type: 'tag'
                            });
                        }
                    });
                };

                ////////////////////
                /// Subscription ///
                ////////////////////

                ////////////////////
                /// Single       ///
                ////////////////////

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

                scope.calculatePager = function (meta) {
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
                        function success (response) {
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
                        function success (response) {
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
                        function success (response) {
                            $timeout(function () {
                                scope.slide.options.data = response.data;

                                if (scope.slide.options.data.endDate) {
                                    var endTimestamp = new Date(scope.slide.options.data.endDate).getTime();
                                    scope.slide.schedule_to = parseInt(endTimestamp / 1000);
                                }
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
