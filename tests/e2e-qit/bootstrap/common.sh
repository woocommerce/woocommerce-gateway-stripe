#!/bin/bash

step() {
	echo
	echo -e "\033[0;34m=>\033[0m $1"
}

output_if_error() {
	out=$("$@" 2>&1)
	if (( $? )); then
        echo $out
    fi
}
