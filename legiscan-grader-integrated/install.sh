#!/bin/bash
# LegiScan Grader Installation Script

echo "=== LegiScan Grader Installation ==="

# Check if WordPress directory exists
if [ ! -d "wp-content" ]; then
    echo "Error: This script must be run from WordPress root directory"
    exit 1
fi

# Create plugin directory
echo "Creating plugin directory..."
mkdir -p wp-content/plugins/legiscan-grader-integrated

# Copy plugin files (assuming they're in current directory)
echo "Copying plugin files..."
cp -r legiscan-grader-integrated/* wp-content/plugins/legiscan-grader-integrated/

# Create uploads directory for bills
echo "Creating uploads directory..."
mkdir -p wp-content/uploads/legiscan-bills/bill

# Set permissions
echo "Setting permissions..."
chmod -R 755 wp-content/plugins/legiscan-grader-integrated
chmod -R 755 wp-content/uploads/legiscan-bills

echo "Installation complete!"
echo ""
echo "Next steps:"
echo "1. Extract your bill.zip to: wp-content/uploads/legiscan-bills/bill/"
echo "2. Go to WordPress Admin → Plugins → Activate 'LegiScan Grader'"
echo "3. Go to LegiScan Grader → Bill Processing to start processing"
echo ""
echo "Plugin is ready to use!"
