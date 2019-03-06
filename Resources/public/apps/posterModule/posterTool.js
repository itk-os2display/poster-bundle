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
