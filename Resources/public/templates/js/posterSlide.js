/**
 * Poster slide.
 */

// Register the function, if it does not already exist.
if (!window.slideFunctions['posterSlide']) {
  window.slideFunctions['posterSlide'] = {
    /**
     * Setup the slide for rendering.
     * @param scope
     *   The slide scope.
     */
    setup: function setupBaseSlide(scope) {
      var slide = scope.ikSlide;

      // Set currentLogo.
      slide.currentLogo = slide.logo;

      // Setup the inline styling
      scope.theStyle = {
        width: "100%",
        height: "100%",
        fontsize: slide.options.fontsize * (scope.scale ? scope.scale : 1.0)+ "px"
      };
    },

    /**
     * Run the slide.
     *
     * @param slide
     *   The slide.
     * @param region
     *   The region object.
     */
    run: function runBaseSlide(slide, region) {
      region.itkLog.info("Running poster slide: " + slide.title);

      var occurrenceIndex = 0;
      var duration = slide.duration !== null ? slide.duration : 15;
      var progressBarDuration = duration;
      if (slide.external_data && slide.external_data.hasOwnProperty('results') && slide.external_data.results.length > 1) {
        progressBarDuration = duration * slide.external_data.results.length;
      }

      function next() {
        occurrenceIndex++;

        if (!slide.external_data || !slide.external_data.hasOwnProperty('results')) {
          // Wait for slide duration, then show next slide.
          // + fadeTime to account for fade out.
          region.$timeout(function () {
            region.nextSlide();
          }, region.fadeTime);
        }
        else if (occurrenceIndex >= slide.external_data.results.length) {
          region.$timeout(function () {
            region.nextSlide();
            occurrenceIndex = 0;
            slide.options.data = slide.external_data.results[0];
          }, region.fadeTime);
        }
        else {
          region.$timeout(function () {
            slide.fadeout = true;
            slide.options.data = slide.external_data.results[occurrenceIndex];
          });
          // Await image load.
          region.$timeout(function () {
            slide.fadeout = false;
          }, 500);
          region.$timeout(function () {
            next();
          }, duration * 1000);
        }
      }

      // Wait fadeTime before start to account for fade in.
      region.$timeout(function () {
        slide.fadeout = false;

        // Set the progress bar animation.
        region.progressBar.start(progressBarDuration);

        region.$timeout(function () {
          console.log(slide.options.data.name);
          next();
        }, duration * 1000);
      }, region.fadeTime);
    }
  };
}
