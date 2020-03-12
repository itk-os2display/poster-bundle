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

                /**
                 * Select type of slide.
                 *
                 * @param type
                 *   'single' or 'subscription'
                 */
                scope.selectType = function (type) {
                    scope.slide.options.type = type;

                    if (type === 'subscription') {
                        initSubscription();
                    }
                };

                ////////////////////
                /// Subscription ///
                ////////////////////

                function setupOptions(type, domId, selectionArray, elements) {
                    for (var i = 0; i < elements.length; i++) {
                        var element = elements[i];
                        scope.selectOptions[type].options.push({
                            id: element['id'],
                            text: element['name']
                        });
                    }

                    jQuery(domId).select2({
                        data: scope.selectOptions[type].options
                    });

                    $(domId).on('select2:select', function (e) {
                        var data = e.params.data;
                        selectionArray[data.id] = data;
                        console.log('select ' + type + ': ' + data.id + " - " + data.text);
                    });

                    $(domId).on('select2:unselect', function (e) {
                        var data = e.params.data;
                        selectionArray[data.id] = null;
                        console.log('unselect ' + type + ': ' + data.id + " - " + data.text);
                    });
                }

                function initSubscription() {
                    scope.loading = true;

                    if (!scope.slide.options.subscription) {
                        scope.slide.options.subscription = {
                            selectedPlaces: {},
                            selectedOrganizers: {},
                            selectedTags: {},
                        }
                    }

                    scope.selectOptions = {
                        places: {
                            text: 'Places',
                            id: 'place',
                            options: []
                        },
                        organizers: {
                            text: 'Organizers',
                            id: 'organizer',
                            options: []
                        },
                        tags: {
                            text: 'Tags',
                            id: 'tag',
                            options: []
                        }
                    };

                    $http.get('/api/os2display_poster/option').then(function (resp) {
                        var data = resp.data;

                        console.log(data);

                        setupOptions('places', '#os2display-poster--select-subscription-places', scope.slide.options.subscription.selectedPlaces, data.places);
                        setupOptions('organizers', '#os2display-poster--select-subscription-organizers', scope.slide.options.subscription.selectedOrganizers, data.organizers);
                        setupOptions('tags', '#os2display-poster--select-subscription-tags', scope.slide.options.subscription.selectedTags, data.tags);

                        /*
                        for (var i = 0; i < data.organizers.length; i++) {
                            var organizer = data.organizers[i];
                            scope.selectOptions[1].subOptions.push({
                                id: organizer['id'],
                                text: organizer['name']
                            });
                        }
                        $timeout(function () {
                            jQuery('#os2display-poster--select-subscription-organizers').select2();
                            $('#os2display-poster--select-subscription-organizers').on('select2:select', function (e) {
                                var data = e.params.data;
                                slide.options.subscription.selectedOrganizers[data.id] = data;
                                console.log('select organizer: ' + data.id + " - " + data.text);
                            });
                            $('#os2display-poster--select-subscription-organizers').on('select2:unselect', function (e) {
                                var data = e.params.data;
                                slide.options.subscription.selectedOrganizers[data.id] = null;
                                console.log('unselect organizer: ' + data.id + " - " + data.text);
                            });
                        });

                        for (var i = 0; i < data.tags.length; i++) {
                            var tag = data.tags[i];
                            scope.selectOptions.tags.push({
                                id: tag['id'],
                                text: tag['name']
                            });
                        }
                        $timeout(function() {
                            jQuery('#os2display-poster--select-subscription-tags').select2();
                            $('#os2display-poster--select-subscription-tags').on('select2:select', function (e) {
                                var data = e.params.data;
                                slide.options.subscription.selectedTags[data.id] = data;
                                console.log('select tag: ' + data.id + " - " + data.text);
                            });
                            $('#os2display-poster--select-subscription-tags').on('select2:unselect', function (e) {
                                var data = e.params.data;
                                slide.options.subscription.selectedTags[data.id] = null;
                                console.log('unselect tag: ' + data.id + " - " + data.text);
                            });
                        });
                        */

                        scope.loading = false;
                    });
                }

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
