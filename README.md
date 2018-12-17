To implement the confirmation, add this to the composer.json of your module:

```
  "require": {
    ...,
    "d3/confirmComposerPackage": "^1.0"
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
