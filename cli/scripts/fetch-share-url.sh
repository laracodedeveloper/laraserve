#!/usr/bin/env bash

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

php $DIR/../laraserve.php fetch-share-url $1 | pbcopy
