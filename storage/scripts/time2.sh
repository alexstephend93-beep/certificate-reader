#!/bin/bash

LOG_FILE="/tmp/numlock_toggle.log"
PID_FILE="/tmp/numlock_toggle.pid"

> ${LOG_FILE}
i=0

cleanup() {
    echo "Script stopped at count: ${i}" >> ${LOG_FILE}
    rm -f ${PID_FILE}
    exit 0
}

trap cleanup SIGTERM SIGINT
echo $$ > ${PID_FILE}

while true; do
    i=$((i + 1))
    xdotool key Num_Lock 2>/dev/null
    sleep 0.1
    xdotool key Num_Lock 2>/dev/null
    echo "${i}" > ${LOG_FILE}
    sleep 5
done
