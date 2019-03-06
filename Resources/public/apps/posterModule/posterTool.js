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
                scope.searchName = '';
                scope.searchUrl = '';

                scope.search = function () {
                    $http.get('/api/os2display_poster/events', {
                        params: {
                            name: scope.searchName,
                            url: scope.searchUrl
                        }
                    }).then(
                        function success(response) {
                            $timeout(function () {
                                scope.events = response.data;
                            });
                        }
                    );
                };

                scope.clickEvent = function (event) {
                    scope.displayEvent = event;
                };

                scope.clickOccurrence = function (occurrence) {
                    scope.slide.options.data = {
                        eventId: scope.displayEvent['@id'],
                        occurrenceId: occurrence['@id'],
                        name: scope.displayEvent.name,
                        image: scope.displayEvent.image,
                        description: scope.displayEvent.description,
                        excerpt: scope.displayEvent.excerpt,
                        startDate: occurrence.startDate,
                        endDate: occurrence.endDate,
                        url: scope.displayEvent.url
                    };

                    scope.close();
                };
            },
            templateUrl: '/bundles/os2displayposter/apps/posterModule/posterTool.html'
        };
    }
]);
