#!/bin/bash

# Need at least: module + scope + one entity
if [ $# -lt 3 ]; then
      echo "Usage: ./generate.sh <ModuleName> <Scope> <Entity1> [Entity2] [Entity3] ..."
  exit 1
fi

moduleName=$1
scope=$2
shift 2   # remove moduleName and scope

actions=("Create" "Update" "Delete" "Show" "List")

echo "Using module: $moduleName"
php artisan module:use "$moduleName"

for entity in "$@"
do
  echo "------------------------------------"
  echo "Generating for: $moduleName -> $scope/$entity"
  echo "------------------------------------"

  php artisan module:make-request "${scope}/${entity}/${entity}Request"
  php artisan module:make-resource "${scope}/${entity}/${entity}Resource"

  for action in "${actions[@]}"
  do
      echo "Creating ${action}${entity}..."

      php artisan module:make-controller "${scope}/${entity}/${action}${entity}Controller" -i
      php artisan module:make-action "${scope}/${entity}/${action}${entity}Action"
  done

done

echo "✅ Generation completed."
