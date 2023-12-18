# Doppler: PDF to JPEG/PNG 

## Overview

Doppler is a PHP class that provides a convenient interface for converting PDF files to images using Ghostscript. It supports both single-page and batch processing, allowing for customization of various parameters.

## Installation

Before using Doppler, ensure that you have Ghostscript and QPDF installed on your system. You can install them using the following commands:

GhostScript:
```bash
sudo apt-get update
sudo apt-get install ghostscript
```

QPDF:
```bash
sudo apt-get install qpdf
```

## Usage

### Example

```php
$doppler = new Doppler();

$doppler
    ->read('path/to/your/file.pdf')
    ->configure([
        'resolution' => 300,
        'compression_quality' => 100,
        'alpha_bits' => 4,
        'disable_color_management' => true,
        'disable_font' => true,
        'disable_annotations' => true,
    ])
    ->process('path/to/output/directory/', 'jpg');
```

### Additional Notes

- The example assumes that the Ghostscript and QPDF executables are in the system's PATH.
- Make sure the web server has the necessary permissions to read the input PDF file and write to the output directory.
