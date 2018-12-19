# Confirmation request plugin for Composer
can used for every kind of questions and packages

[![License](https://img.shields.io/packagist/l/d3/confirmcomposerpackage.svg)](https://packagist.org/packages/d3/confirmcomposerpackage)
[![Latest Stable Release](https://img.shields.io/packagist/v/d3/confirmcomposerpackage.svg?label=latest%20stable)](https://packagist.org/packages/d3/confirmcomposerpackage)

To implement the confirmation, add this to the composer.json of your package:

```
  "require": {
    ...,
    "d3/confirmcomposerpackage": "^1.0"
  },
  "extra": {
    "packageConfirmation": {
      "question": "your confirmation question (y/N) ",
      "acceptedanswers": [
        "y",
        "yes"
      ]
    }
  },
```
