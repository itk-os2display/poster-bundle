# OS2display PosterBundle
Creates a poster from a source for OS2display.

## Contained in bundle
* Poster template.
* Slide tool to choose event from source.
* Integration with Eventdatabasen: https://github.com/itk-event-database/event-database-api
* Cron process to keep data in template up to date.

## Configuration options
Add this to config.yml.
```
os2_display_poster:
  # Number of seconds between updating the data for the slides.
  # Defaults to 900.
  cron_interval: 1800
  # List of providers to search in. Atm. the system only supports one.
  providers:
    eventdatabasen:
      name: Eventdatabasen
      url: [ END_POINT ]

```
