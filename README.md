# LegiScan Grader - Enhanced with Bill Folder Integration

A comprehensive WordPress plugin for grading and analyzing legislative bills using LegiScan API data with interactive maps, tables, and bill folder processing support.

## Version 2.1.0 - Enhanced Integration

### New Features in v2.1.0
- **Bill Folder Integration**: Automatically processes bills from folder structure (bill/STATE/*.json)
- **Enhanced Grading Engine**: Comprehensive criminal justice reform analysis with 40+ criteria
- **State-by-State Analysis**: Process and grade bills by individual states
- **Batch Processing**: Handle thousands of bills efficiently
- **Advanced Export Options**: CSV and JSON export with detailed breakdowns
- **Real-time Processing Dashboard**: Monitor processing status and progress

## Features

### Core Functionality
- **Interactive State Scorecard Map**: Visual representation of state criminal justice grades
- **Advanced Bill Processing**: Handles both flat file structure and organized state folders
- **Comprehensive Grading System**: 
  - Positive keywords (reform, rehabilitation, treatment, etc.) +10-15 points each
  - Negative keywords (mandatory minimum, three strikes, etc.) -15-25 points each
  - Status analysis, sponsor count, voting patterns
  - Subject-based scoring
- **Export Capabilities**: State scorecards, individual bill grades, comprehensive reports
- **API Integration**: LegiScan and Census API support with usage monitoring

### Bill Processing
- Supports 9,372+ bills from all 50 states
- Automatic detection of bill folder structure
- JSON parsing with error handling
- Batch processing with progress tracking
- State-specific analysis and rankings

### Grading Criteria
The enhanced grading engine evaluates bills based on:

#### Positive Keywords (Add Points)
- Reform & Rehabilitation: reform (+15), rehabilitation (+15), treatment (+12)
- Diversion & Alternatives: diversion (+15), restorative justice (+15), drug court (+12)
- Reentry & Second Chances: expungement (+15), reentry (+15), ban the box (+12)
- Sentencing Reform: sentencing reform (+15), sentence reduction (+12)

#### Negative Keywords (Subtract Points)
- Harsh Sentencing: mandatory minimum (-20), three strikes (-20), death penalty (-25)
- Punitive Measures: solitary confinement (-15), private prison (-15)

#### Additional Scoring
- Bill Status: Enacted (+15), Passed Both (+8), Committee (+4)
- Sponsor Support: 10+ sponsors (+10), 5+ sponsors (+7)
- Voting Patterns: 80%+ support (+15), 60%+ support (+10)

## Installation

### Requirements
- WordPress 5.0 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher

### Installation Steps

1. **Upload Plugin Files**
   ```
   wp-content/plugins/legiscan-grader-integrated/
   ```

2. **Activate Plugin**
   - Go to WordPress Admin → Plugins
   - Find "LegiScan Grader - Enhanced"
   - Click "Activate"

3. **Setup Bill Data**
   - Extract your bill.zip to: `wp-content/uploads/legiscan-bills/bill/`
   - The structure should be: `legiscan-bills/bill/STATE/*.json`
   - Example: `legiscan-bills/bill/CA/AB123.json`

4. **Configure API Keys** (Optional)
   - Go to LegiScan Grader → Settings
   - Add LegiScan API key for live data
   - Add Census API key for demographic data

## Usage

### Processing Bills

1. **Access Bill Processing Dashboard**
   - Go to WordPress Admin → LegiScan Grader → Bill Processing
   - View statistics: Total states, total bills

2. **Process All Bills**
   - Click "Process All Bills" to analyze all 9,372+ bills
   - Monitor progress in real-time
   - View processing status and results

3. **Process Individual States**
   - Click on individual state cards to process specific states
   - Useful for testing or incremental processing

### State Analysis

1. **View State Rankings**
   - Go to LegiScan Grader → State Analysis
   - See states ranked by criminal justice reform scores
   - View grade distributions (A, B, C, D, F)

2. **Analyze Results**
   - Top-performing states in criminal justice reform
   - Grade breakdowns and statistics
   - Comparative analysis across states

### Export Data

1. **State Scorecard (CSV)**
   - State-by-state grades and statistics
   - Perfect for spreadsheet analysis

2. **All Bills with Grades (CSV)**
   - Individual bill scores and grades
   - Keyword matches and analysis details

3. **Comprehensive Report (JSON)**
   - Complete analysis with detailed breakdowns
   - API-friendly format for further processing

### Shortcodes

Display data on your website using shortcodes:

```php
// Interactive state map
[legiscan_scorecard_map]

// Bills table
[legiscan_bills_table]

// State summary
[legiscan_state_summary state="CA"]
```

## File Structure

```
legiscan-grader-integrated/
├── legiscan-grader.php          # Main plugin file
├── includes/
│   ├── bill-manager.php         # Enhanced bill processing
│   ├── grading-engine.php       # Comprehensive grading system
│   ├── legiscan-api.php         # API integration
│   ├── census-api.php           # Census data integration
│   ├── api-usage.php            # API usage monitoring
│   ├── scorecard-map.php        # Interactive map rendering
│   └── admin-page.php           # Admin interface
├── admin/
│   └── admin-page.php           # Main admin page
├── assets/
│   ├── css/
│   │   ├── legiscan-grader.css  # Frontend styles
│   │   └── admin-style.css      # Admin styles
│   └── js/
│       ├── legiscan-grader.js   # Frontend JavaScript
│       └── admin-script.js      # Admin JavaScript
└── README.md                    # This file
```

## Data Structure

### Expected Bill Folder Structure
```
wp-content/uploads/legiscan-bills/bill/
├── AK/
│   ├── HB101.json
│   ├── HB104.json
│   └── ...
├── CA/
│   ├── AB123.json
│   ├── SB456.json
│   └── ...
└── [All 50 states]/
    └── [Bill files].json
```

### Bill JSON Format
Each bill JSON should contain:
```json
{
  "bill": {
    "bill_id": "123456",
    "bill_number": "HB101",
    "title": "Criminal Justice Reform Act",
    "description": "A bill to reform sentencing...",
    "status": 4,
    "state": "AK",
    "sponsors": [...],
    "subjects": [...],
    "votes": [...]
  }
}
```

## Troubleshooting

### Common Issues

1. **Bills Not Loading**
   - Check file permissions on uploads directory
   - Verify bill folder structure matches expected format
   - Check WordPress error logs

2. **Processing Timeout**
   - Increase PHP max_execution_time
   - Process states individually instead of all at once
   - Check server memory limits

3. **Export Not Working**
   - Verify user permissions (manage_options capability)
   - Check for PHP output buffering issues
   - Ensure sufficient disk space

### Performance Optimization

- **Large Datasets**: Process states individually for better performance
- **Memory Usage**: Increase PHP memory_limit for processing all bills
- **Caching**: Results are cached to improve subsequent loads

## Support

For issues, questions, or feature requests:
- Check WordPress error logs
- Verify file permissions and structure
- Test with smaller datasets first

## License

GPL v2 or later

## Changelog

### v2.1.0 (Current)
- Added bill folder structure support
- Enhanced grading engine with 40+ criteria
- State-by-state processing and analysis
- Advanced export options
- Real-time processing dashboard
- Comprehensive criminal justice reform analysis

### v2.0.0 (Original)
- Initial LegiScan integration
- Basic grading system
- Interactive maps
- API integration
