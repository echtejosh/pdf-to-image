# Doppler: PDF to Image Converter

![Doppler Logo](https://your-image-url.png)

## Overview

Doppler is a PHP class that provides a convenient interface for converting PDF files to images using Ghostscript. It supports both single-page and batch processing, allowing for customization of various parameters.

**Author:** echtyushi  
**Version:** 1.0

## Installation

Before using Doppler, ensure that you have Ghostscript and qpdf installed on your system. You can install them using the following commands:

```bash
# Install Ghostscript
sudo apt-get update
sudo apt-get install ghostscript

# Install qpdf
sudo apt-get install qpdf
```

## Usage

### Example

```php
<?php

// Include the Doppler class
require_once('path/to/Doppler.php');

// Create an instance of Doppler
$doppler = new Doppler();

// Set the PDF file to be processed
$doppler->read('path/to/your/file.pdf');

// Configure options (optional)
$config = [
    'resolution' => 300,
    'compression_quality' => 100,
    'alpha_bits' => 4,
    'disable_color_management' => true,
    'disable_font' => true,
    'disable_annotations' => true,
];

$doppler->configure($config);

// Process the PDF file and generate images in the specified directory
try {
    $doppler->process('path/to/output/directory/', 'jpg');
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
?>
```

## Configuration Options

Doppler provides various configuration options that can be customized according to your needs. You can set these options using the `configure` method.

```php
// Example configuration
$config = [
    'resolution' => 300,
    'compression_quality' => 100,
    'alpha_bits' => 4,
    'disable_color_management' => true,
    'disable_font' => true,
    'disable_annotations' => true,
];

$doppler->configure($config);
```

### Additional Notes

- The example assumes that the Ghostscript and qpdf executables are in the system's PATH.
- Make sure the web server has the necessary permissions to read the input PDF file and write to the output directory.

## License

This project is licensed under the [MIT License](LICENSE).

---

Feel free to customize this README based on your project's needs.
