#!/bin/bash

OUTPUT=".docs/fluxio-structure.txt"

tree -a -L 5 \
-I 'vendor|node_modules|.git|.nuxt|storage|bootstrap/cache|dist|build|coverage|public/build|.output' \
> "$OUTPUT"

echo "Project structure exported to $OUTPUT"