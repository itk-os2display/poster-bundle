/**
 * Poster tool. Select between subscription or single.
 */
angular.module('posterModule').directive('posterTool', [
    function () {
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
                /**
                 * Select type of slide.
                 *
                 * @param type
                 *   'single' or 'subscription'
                 */
                scope.selectType = function (type) {
                    scope.slide.options.type = type;
                };
            },
            templateUrl: '/bundles/os2displayposter/apps/posterModule/posterTool.html'
        };
    }
]);

angular.module('posterModule').directive('posterToolSingle', [
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
                scope.typeSelect = 'searchName';
                scope.displayOverrides = false;

                if (!scope.slide.options.overrides) {
                    scope.slide.options.overrides = {};
                }

                function setupFilter(type) {
                    var element = jQuery('#os2display-poster--select-single-' + type);

                    element.select2({
                        ajax: {
                            url: '/api/os2display_poster/search',
                            dataType: 'json',
                            delay: 500,
                            data: function (params) {
                                return {
                                    name: params.term,
                                    page: params.page || 1,
                                    type: type
                                };
                            }
                        },
                        minimumInputLength: 1
                    });
                }
                $timeout(function () {
                    setupFilter('organizers');
                    setupFilter('places');
                    setupFilter('tags');
                }, 1000);

                scope.toggleOverrides = function () {
                    scope.displayOverrides = !scope.displayOverrides;
                };

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

                scope.search = function (typeSelect, searchName, searchUrl, searchOrganizer, searchPlace, searchTag, page) {
                    scope.showSpinner = true;
                    scope.displayEvent = null;
                    scope.events = null;

                    var params = {};

                    if (page) {
                        params.page = page;
                    }

                    if (typeSelect === 'searchName') {
                        params.name = searchName;
                    }
                    else if (typeSelect === 'searchUrl') {
                        params.url = searchUrl;
                    }
                    else if (typeSelect === 'searchOrganizer') {
                        var organizerOption = jQuery('#os2display-poster--select-single-organizers option:selected');
                        params.organizer = organizerOption.val();
                    }
                    else if (typeSelect === 'searchPlace') {
                        var placeOption = jQuery('#os2display-poster--select-single-places option:selected');
                        params.place = placeOption.val();
                    }
                    else if (typeSelect === 'searchTag') {
                        var tagOption = jQuery('#os2display-poster--select-single-tags option:selected');
                        params.tag = tagOption.text();
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
            templateUrl: '/bundles/os2displayposter/apps/posterModule/posterToolSingle.html'
        };
    }
]);

angular.module('posterModule').directive('posterToolSubscription', [
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
                function getSubscriptionResults() {
                    scope.loadingSearchResults = true;
                    $timeout(function () {
                        var selections = angular.copy(scope.slide.options.subscription);

                        var search = {
                            tags: [],
                            places: [],
                            organizers: [],
                            numberOfResults: selections.selectedNumber
                        };

                        for (var tag in selections.selectedTags) {
                            tag = selections.selectedTags[tag];
                            if (tag !== undefined && tag !== null) {
                                search.tags.push(tag.id);
                            }
                        }

                        for (var org in selections.selectedOrganizers) {
                            org = selections.selectedOrganizers[org];
                            if (org !== undefined && org !== null) {
                                search.organizers.push(org.id);
                            }
                        }

                        for (var place in selections.selectedPlaces) {
                            place = selections.selectedPlaces[place];
                            if (place !== undefined && place !== null) {
                                search.places.push(place.id);
                            }
                        }

                        $http.get('/api/os2display_poster/search_events', {
                            params: search
                        }).then(
                            function (resp) {
                                var data = resp.data.results;

                                $timeout(function () {
                                    scope.subscription.foundEvents = data;

                                    if (scope.subscription.foundEvents.length > 0) {
                                        scope.slide.options.data = scope.subscription.foundEvents[0].occurrence;
                                    }
                                });

                                scope.loadingSearchResults = false;
                            },
                            function (err) {
                                console.log('error', err);
                            }
                        )
                    });
                }

                function setupFilter(type, selectionArray) {
                    var element = jQuery('#os2display-poster--select-subscription-' + type);

                    element.select2({
                        ajax: {
                            url: '/api/os2display_poster/search',
                            dataType: 'json',
                            delay: 500,
                            data: function (params) {
                                return {
                                    name: params.term,
                                    page: params.page || 1,
                                    type: type
                                };
                            }
                        },
                        minimumInputLength: 1
                    });

                    element.on('select2:select', function (e) {
                        var data = e.params.data;
                        selectionArray[data.id] = data;
                        getSubscriptionResults();
                    });

                    element.on('select2:unselect', function (e) {
                        var data = e.params.data;
                        selectionArray[data.id] = null;
                        getSubscriptionResults();
                    });

                    // Select previous selections.
                    for (var selection in selectionArray) {
                        selection = selectionArray[selection];
                        var newOption = new Option(selection.text, selection.id, true, true);
                        element.append(newOption).trigger('change');
                    }
                }

                scope.loading = true;
                scope.subscription = {};

                scope.numberItems = [1,2,3,4,5,6,7,8,9,10];

                // Default selections.
                if (!scope.slide.options.subscription) {
                    scope.slide.options.subscription = {
                        selectedPlaces: {},
                        selectedOrganizers: {},
                        selectedTags: {},
                        selectedNumber: 5,
                    }
                }

                // Hack: Delay to make sure the template has been loaded.
                $timeout(function () {
                    setupFilter('places', scope.slide.options.subscription.selectedPlaces);
                    setupFilter('organizers', scope.slide.options.subscription.selectedOrganizers);
                    setupFilter('tags', scope.slide.options.subscription.selectedTags);

                    $('#os2display-poster--select-number').on('change', getSubscriptionResults);

                    scope.loading = false;
                }, 1000);

                getSubscriptionResults();
            },
            templateUrl: '/bundles/os2displayposter/apps/posterModule/posterToolSubscription.html'
        };
    }
]);
