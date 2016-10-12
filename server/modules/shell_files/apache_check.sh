#!/bin/bash

result=$(ps -e | grep "apache2" | wc -l)

echo result
