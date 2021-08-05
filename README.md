# OpenEuropa oEmbed

The OpenEuropa oEmbed module allows the embedding of entities into content in an agnostic (non-Drupal) way.

To this end, it comes with two main elements: the WYSIWYG embed button and the filter plugin. On top of that, it allows
the site administrators to define which available view displays are also available to be embedded (and whether they should be embedded inline or as a block).

## WYSIWYG button

Using the embed WYSIWYG button, editors can select entities that they wish to embed. Upon selecting the entity, an oEmbed-based embed code is
inserted in the content. This code follows the oEmbed protocol and is therefore understandable by other clients as well. An example embed code (for media entities in this case):

```
<p data-oembed="https://oembed.ec.europa.eu?url=https%3A//data.ec.europa.eu/ewp/media/118a06e9-e7df-4b7b-8ab2-5f5addc2f0b3">
  <a href="https://data.ec.europa.eu/ewp/media/118a06e9-e7df-4b7b-8ab2-5f5addc2f0b3">The media title</a>
</p>
```

Where `118a06e9-e7df-4b7b-8ab2-5f5addc2f0b3` is the UUID of the media entity.

For nodes, it would look like this:


```
<p data-oembed="https://oembed.ec.europa.eu?url=https%3A//data.ec.europa.eu/ewp/node/118a06e9-e7df-4b7b-8ab2-5f5addc2f0b3">
  <a href="https://data.ec.europa.eu/ewp/node/118a06e9-e7df-4b7b-8ab2-5f5addc2f0b3">The node title</a>
</p>
```

While an inline embed would look like this:

```
<a data-oembed="https://oembed.ec.europa.eu?url=https%3A//data.ec.europa.eu/ewp/media/118a06e9-e7df-4b7b-8ab2-5f5addc2f0b3" href="https://data.ec.europa.eu/ewp/media/118a06e9-e7df-4b7b-8ab2-5f5addc2f0b3">sdasd</a>
```


Moreover, there is also the [OpenEuropa oEmbed server] submodule which will can used when a site expects its content to be read by external systems that
need to understand the embed codes. Essentially, it acts as an oEmbed provider for the media resources on the site.

## Text filter

The embed code provided by the WYSIWYG button is transformed into the rendered entity by the filter plugin `FilterOembed`.
Adding this to a text format will replace the embed tags with the rendered entity.


## Embeddable view displays

Site administrators will need to define which available view modes are also available to be embedded via the tools described above.
This is done by selecting the available view displays on the display mode configuration page for each available media bundle.

## Usage

In order to use the functionalities of the module, follow the next steps:

1) Create an embed button that uses the `Drupal entities` embed type.

2) Create a text format.
You can do so by navigating to `/admin/config/content/formats` and clicking the "Add text format" button.

3) Add the embed button to your Active toolbar.
You can do that while creating your text format or by navigating to the text format configuration form (`/admin/config/content/formats/manage/TEXT_FORMAT_ID`).
Make sure you select CKEditor as the Text editor for your text format and move the button from the *available buttons* section to the Active toolbar.

4) Enable the "Embeds entities using the oEmbed format" filter.
This filter needs to be enabled and placed last in the Filter processing order.
(**WARNING**: This is very important if you want the oEmbed specific urls to be converted into internal aliases)

5) Make view displays embeddable.
Once the previous steps are done, navigate to the display mode configuration of the bundle you wish to be embeddable and select which of
the available view displays will be available for embedding. E.g., in order to configure which of the view displays of the Image media type
are available for embedding, you will need to navigate to `/admin/structure/media/manage/image/display`.

## Development setup

You can build the development site by running the following steps:

* Install the Composer dependencies:

```bash
composer install
```

A post command hook (`drupal:site-setup`) is triggered automatically after `composer install`.
It will make sure that the necessary symlinks are properly setup in the development site.
It will also perform token substitution in development configuration files such as `behat.yml.dist`.

**Please note:** project files and directories are symlinked within the test site by using the
[OpenEuropa Task Runner's Drupal project symlink](https://github.com/openeuropa/task-runner-drupal-project-symlink) command.

If you add a new file or directory in the root of the project, you need to re-run `drupal:site-setup` in order to make
sure they are be correctly symlinked.

If you don't want to re-run a full site setup for that, you can simply run:

```
$ ./vendor/bin/run drupal:symlink-project
```

* Install test site by running:

```bash
./vendor/bin/run drupal:site-install
```

The development site web root should be available in the `build` directory.

### Using Docker Compose

Alternatively, you can build a development site using [Docker](https://www.docker.com/get-docker) and
[Docker Compose](https://docs.docker.com/compose/) with the provided configuration.

Docker provides the necessary services and tools such as a web server and a database server to get the site running,
regardless of your local host configuration.

#### Requirements:

- [Docker](https://www.docker.com/get-docker)
- [Docker Compose](https://docs.docker.com/compose/)

#### Configuration

By default, Docker Compose reads two files, a `docker-compose.yml` and an optional `docker-compose.override.yml` file.
By convention, the `docker-compose.yml` contains your base configuration and it's provided by default.
The override file, as its name implies, can contain configuration overrides for existing services or entirely new
services.
If a service is defined in both files, Docker Compose merges the configurations.

Find more information on Docker Compose extension mechanism on [the official Docker Compose documentation](https://docs.docker.com/compose/extends/).

#### Usage

To start, run:

```bash
docker-compose up
```

It's advised to not daemonize `docker-compose` so you can turn it off (`CTRL+C`) quickly when you're done working.
However, if you'd like to daemonize it, you have to add the flag `-d`:

```bash
docker-compose up -d
```

Then:

```bash
docker-compose exec web composer install
docker-compose exec web ./vendor/bin/run drupal:site-install
```

Using default configuration, the development site files should be available in the `build` directory and the development site
should be available at: [http://127.0.0.1:8080/build](http://127.0.0.1:8080/build).

#### Running the tests

To run the grumphp checks:

```bash
docker-compose exec web ./vendor/bin/grumphp run
```

To run the phpunit tests:

```bash
docker-compose exec web ./vendor/bin/phpunit
```

To run the behat tests:

```bash
docker-compose exec web ./vendor/bin/behat
```

#### Step debugging

To enable step debugging from the command line, pass the `XDEBUG_SESSION` environment variable with any value to
the container:

```bash
docker-compose exec -e XDEBUG_SESSION=1 web <your command>
```

Please note that, starting from XDebug 3, a connection error message will be outputted in the console if the variable is
set but your client is not listening for debugging connections. The error message will cause false negatives for PHPUnit
tests.

To initiate step debugging from the browser, set the correct cookie using a browser extension or a bookmarklet
like the ones generated at https://www.jetbrains.com/phpstorm/marklets/.

## Contributing

Please read [the full documentation](https://github.com/openeuropa/openeuropa) for details on our code of conduct, and the process for submitting pull requests to us.

## Versioning

We use [SemVer](http://semver.org/) for versioning. For the available versions, see the [tags on this repository](https://github.com/openeuropa/oe_oembed/tags).
