# n98-magerun module: Magento cache backend benchmark

This script was ported from great Magento cache benchmark script provided by Colin Mollenhour.
You can find the original here: [https://github.com/colinmollenhour/magento-cache-benchmark/](https://github.com/colinmollenhour/magento-cache-benchmark/)

This script only works as n98-magerun module.

## Installation in your user module folder

    mkdir -p ~/.n98-magerun/modules
    cd ~/.n98-magerun/modules
    git clone https://github.com/cmuench/magerun-module-cache-benchmark.git

## Usage

Run the init command.

    $> n98-magerun.phar cache:benchmark:init

There are many parameter. To see a complete list run:

    $> n98-magerun.phar cache:benchmark:init --help

To test the tags run:

    $> n98-magerun.phar cache:benchmark:tags

To analyse your cache run:

    $> n98-magerun.phar cache:benchmark:analyse

To test compression libs run:

    $> n98-magerun.phar cache:benchmark:comp_test <lib>
