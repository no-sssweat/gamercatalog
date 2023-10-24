# Google Analytics Counter

## Introduction

The Google Analytics Counter module is a scalable, lightweight page view counter which stores data collected by Google Analytics API in Drupal. The goal of the module is for the pageviews in Drupal to match the Pageviews in Google Analytics.

This module does its work during cron.

## The module's features are

The module's features are:
- Creation of a custom field which contains the Pageviews from Google Analytics API.
  - Once cron has populated the custom field, it can be used like any other field. This makes the Google Analytics Counter field usable for Drupal views or inclusive on Drupal page displays.
  - Views can also be based directly on the Google Analytics Counter storage tables, but using the custom field makes much more sense.
- Creates a customizable block which also contains the Pageviews from Google Analytics API.
- A text filter which makes a token available. The token contains the Pageviews from Google Analytics API.
- The data saved in Drupal can also be used for other things, like search.

This module is suitable for large and high-traffic sites.

## Installation and usage

Please check online documentation: https://www.drupal.org/docs/contributed-modules/google-analytics-counter/google-analytics-counter-40

## Disclaimer

Drupal 9 and 10 port was created by Róbert Kasza and EGM s.r.o.

The Drupal 8 port of this module was created by Eric Sod for Memorial Sloan Kettering Cancer Center.

The Drupal 7 version of the module was developed by Tomáš Fülöpp / Vacilando. Any and all donations are welcome via GitTip. The maintainer is available for paid customizations of any Vacilando module, development of new modules, or urgent troubleshooting / patch reviewing. Development of this branch has been partly sponsored by Australian Policy Online (APO) at Swinburne University of Technology.
