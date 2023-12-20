# NYPL Refile Request Service

[![Build Status](https://travis-ci.org/NYPL/refile-request-service.svg?branch=master)](https://travis-ci.org/NYPL/refile-request-service)
[![Coverage Status](https://coveralls.io/repos/github/NYPL/refile-request-service/badge.svg?branch=master)](https://coveralls.io/github/NYPL/refile-request-service?branch=master)

See [wiki](https://github.com/NYPL/refile-request-service/wiki) for documentation of purpose and endpoints served. [Additional context can be found here.](https://docs.google.com/document/d/1HtthNU6spmhV8TKCQEDWQ4kQvzdRd3UTfsVCFOLmvMA)

This service provides access to "refiles" (clearing the status of items). It's build with the [NYPL PHP Microservice Starter](https://github.com/NYPL/php-microservice-starter).

This package adheres to [PSR-1](http://www.php-fig.org/psr/psr-1/), [PSR-2](http://www.php-fig.org/psr/psr-2/), and [PSR-4](http://www.php-fig.org/psr/psr-4/) (using the [Composer](https://getcomposer.org/) autoloader).

## Service Responsibilities

This is a PHP Lambda for serving `/api/v0.1/refile-requests`. A "refile request" is a request to clear the status of an item, typically because it has been reshelved at ReCAP. Refiling changes an item that is checked out or "in transit" to "Available".

See [#sip2-known-issues](SIP2 Known Issues)

## Running Locally

There are two ways to run the app locally.

1. **Running locally with docker-compose** launches the app on a PHP base image connected to a clean local database. This method should be sufficient for most development work.
2. **Running locally with SAM** launches the app on a Lambda-specific Node base image, making it as close as possible to the deployment environment. This method will usually be overkill, but may be helpful when debugging/developing the Node wrapper.

For both methods, you should add an entry to your `/etc/hosts` with "127.0.0.1 local.nypl.org" if you don't already have one.

Note that, at writing, SIP2 calls to both the Test and Prod Sierra servers are not allowed on/off-site (with/without VPN). Consequently, the app will hang for most requests when running locally. You can enable `SKIP_SIP2` (i.e. either in `sam.local.yml` or `config/var_local.env`) to entirely skip SIP2 calls when running the app locally if doing so doesn't invalidate your testing.

### Running locally with docker-compose

To start the PHP app connected to a fresh local psql database:

```
  AWS_ACCESS_KEY_ID=... AWS_SECRET_ACCESS_KEY=... docker-compose up
```

You should supply AWS creds from the `nypl-digital-dev` account so that the app can decrypt config from `./config/var_local.env`

Navigate to http://local.nypl.org:8084/api/v0.1/recap/refile-requests

Note that use of docker-compose should be sufficient for testing local application code, but does not emulate running the app on a Node image as it does when deployed. This subtle environment difference should not matter unless something in the Node layer is under examination.

Note that because SIP2 connections are blocked from all but AWS infractructure, you can not perform a successful test of the app locally at writing. You can, however, perform a not successful test. To _attempt_ to perform a refile request, POST to `https://local.nypl.org:8084/api/v0.1/recap/refile-requests`:
```
{
  "itemBarcode": "33433073352803"
}
```

The process will hang for 30s and then mark the refile-request as failed (viewable at the GET endpoint noted above). You may use the `SKIP_SIP2` env var to slightly improve this testing experience.

To log into the running container:
```
docker exec -it refile-request-service_php_1 bash
```

To connect to the local db:
```
docker exec -it refile-service-postgres-db psql -U postgres refile_requests_local
```

If you need to just build the app image described in the `Dockerfile`:
```
docker image build -t refile-request-service:local .
```

### Running locally with SAM

To more fully emulate running the app in Node image:

Start local test postgres db:

```
docker-compose up -d db
```

Edit `sam.local.yml` and change the `DB_CONNECT_STRING` to use your current public IP in place of "[YOURIP]". In OSX, your public IP is available via `ifconfig -u | grep 'inet ' | grep -v 127.0.0.1 | cut -d\  -f2 | head -1`.

Invoke on arbitrary event:
```
sam local invoke --profile nypl-digital-dev -t sam.local.yml -e sample/get-all.json
```

## Configuration

Various files are used to configure and deploy the Lambda.

### .env

`.env` is used by `node-lambda` for deploying to and configuring Lambda in *all* environments. You should use this file to configure the common settings for the Lambda (e.g. timeout, role, etc.) and include AWS credentials to deploy the Lambda. These parameters are ultimately set by the [var environment files](#var_environment) when the Lambda is deployed.

### ./config/var_*environment*.env

Configures environment variables specific to each environment. These are not actually deployed. They are read by `node-lambda` at deploy time to push environmental variables to the app.

./config/var_local.env is a special environment config file in that it isn't used to set the config of any deployed app. It will be used when running the app via `docker-compose`. It should generally match QA config. The `Config` class in microservice-starter will read from this file when it detects it's running locally (which it determines by the _absense_ of a `LAMBDA_TASK_ROOT` env var, which is available in deployed code).

### ./config/event_sources_*environment*.json

Configures Lambda event sources (triggers) specific to each environment. This is used by `node-lambda` at deploy time to configure the app with the right triggers.

## Deployment

This app uses `qa` and `production` deployment branches. On push, Travis CI invokes `npm run deploy-[environment]`, which in turn invokes `node-lambda`.

You may also manually use "manual" versions of the npm commands (see [package.json](package.json), which differ from the primary deploy scripts only in using a named `nypl-digital-dev` AWS profile.

## Tests

To run tests (very little coverage):
```
docker image build -t refile-request-service:local --target tests .
```

Note that test output will be noisy for failures but rather subtle if all tests pass:
```
 => [test 1/1] RUN ["./vendor/bin/phpunit", "tests"] .4s
```

If you have a local PHP 7.1 in your PATH, you can also just run the tests directly:

```
./vendor/bin/phpunit --bootstrap vendor/autoload.php
```

## Contributing

This repo uses the [Development-QA-Main Git Workflow](https://github.com/NYPL/engineering-general/blob/master/standards/git-workflow.md#development-qa-main)

## Known issues

### SIP2 Known Issues

Refile is implemented as a SIP2 Checkin call to our ILS. This call generally has the effect of changing an item status to '-' ("Available") regardless of the status, with a few exceptions. The set of statuses we're most interested in are:

| Name                     | Status Code | Has due date? | Item level holds? |
|--------------------------|-------------|---------------|-------------------|
| Available w/out holds    | -           | false         | false             |
| Available with holds     | -           | false         | true              |
| Checked out w/out holds  | -           | true          | true              |
| Checked out with holds*  | -           | true          | false             |
| In transit w/out holds   | t           | false         | false             |
| In transit with holds*   | t           | false         | true              |
| On holdshelf             | !           | false         | true              |

\* Note that while it's technically possible to create an item-level hold on an item that is checked out or in transit, it's generally not possible with any public facing interface.

The effect of running SIP2 Checkin on the above statuses follows:

| Name                     | Effect of SIP2 Checkin                  |
|--------------------------|-----------------------------------------|
| Available w/out holds    | No change                               |
| Available with holds     | Status change to 'On holdshelf'. Avoid! |
| Checked out w/out holds  | Status change to 'Available'            |
| Checked out with holds   | Status change to 'On holdshelf'. Avoid! |
| In transit w/out holds   | Status change to 'Available'            |
| In transit with holds    | Status change to 'On holdshelf'. Avoid! |
| On holdshelf             | No change                               |

To avoid placing items on holdshelf, the presence of an active item-level hold causes this app to entirely skip the the SIP2 Checkin call and log the incident in the `af_message` column, where it will surface in SCSBuster. The following indicates which item statuses result in a [typically] successful SIP2 Checkin call (✅), and which scenarios we're intentionally marking as failed without attempting any SIP2 Checkin call (🛑):

| Name                     | Result of refile endpoint          |
|--------------------------|------------------------------------|
| Available w/out holds    | ✅ No change                       |
| Available with holds     | 🛑 Refile error; No change to item |
| Checked out w/out holds  | ✅ Status change to 'Available'    |
| Checked out with holds   | 🛑 Refile error; No change to item |
| In transit w/out holds   | ✅ Status change to 'Available'    |
| In transit with holds    | 🛑 Refile error; No change to item |
| On holdshelf             | 🛑 Refile error; No change to item |

These issues are discussed to a degree in [the Refile Overview document](https://docs.google.com/document/d/1HtthNU6spmhV8TKCQEDWQ4kQvzdRd3UTfsVCFOLmvMA/edit#). The fact that SIP2 is unable to clear 'ON HOLDSHELF' is noted under "20170818 testing SIP2 Checkin to clear on holdshelf". The behavior whereby SIP2 Checkin causes items with holds to be placed 'ON HOLDSHELF' is not noted.
