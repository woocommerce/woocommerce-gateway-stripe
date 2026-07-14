# Testing instructions

## Running PHP Unit tests on a local machine

**Set up**

1. Start the local environment (_docker containers_) with:
```
npm run up
```

**Running the tests**

Run the tests with:
```
npm run test:php
```

**Running tests for a single class**

To run tests for a single class, use the following command:
```
npm run test:php -- --filter=ClassName
```
Replace `ClassName` with the name of the class you want to test.
