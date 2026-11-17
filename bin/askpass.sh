#!/bin/sh
case "$1" in
    *[Uu]sername*) printf '%s\n' "${ZCTZGIT_USERNAME:-x-access-token}" ;;
    *) printf '%s\n' "${ZCTZGIT_TOKEN}" ;;
esac
