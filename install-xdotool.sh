#!/bin/bash

echo "Installing xdotool for Num Lock toggling..."

# Check if running on Linux
if [[ "$OSTYPE" == "linux-gnu"* ]]; then
    # Install xdotool
    if command -v apt-get &> /dev/null; then
        sudo apt-get update
        sudo apt-get install -y xdotool
    elif command -v yum &> /dev/null; then
        sudo yum install -y xdotool
    elif command -v dnf &> /dev/null; then
        sudo dnf install -y xdotool
    else
        echo "Please install xdotool manually for your distribution"
        exit 1
    fi
else
    echo "This script is only for Linux. Num Lock toggling may not work on your OS."
    exit 1
fi

echo "xdotool installed successfully!"

# Create scripts directory
mkdir -p storage/scripts

# Create the numlock toggle script
cat > storage/scripts/time2.sh << 'EOF'
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
EOF

chmod +x storage/scripts/time2.sh

echo "Script created at storage/scripts/time2.sh"
echo "You can now use the Num Lock toggle feature in your dashboard!"