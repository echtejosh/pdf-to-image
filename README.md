# Doppler 

Doppler is a PHP class that provides a convenient interface for converting PDF files to images using Ghostscript. It supports both single-page and batch processing, allowing for customization of various parameters.

## Installation

Before using Doppler, ensure that you have Ghostscript and QPDF installed on your system. You can install them using the following commands:

```bash
sudo apt-get install ghostscript
sudo apt-get install qpdf
```

## Usage
Example on how to convert a PDF to JPG:
```php
$doppler = new Doppler();

$doppler->read('example.pdf');

$doppler->configure([
    'resolution' => 300,
    'compression_quality' => 100,
]);

$doppler->process('path/to/folder/', 'jpg');
```

## Functions

### read(path)
Set a PDF to be read and converted. Referenced as `path`:
```php
$doppler->read(path);
```

### configure(options)
Configure the conversion process for the PDF. See [options](https://github.com/echtyushi/doppler/blob/master/README.md#options):
```php
$doppler->configure(options);
```

### process(directory, type)
Convert to `type` which can either be JPG or PNG the given directory:
```php
$doppler->process(directory, type);
```

### get_command()
Get the Ghostscript command for processing the PDF file:
```php
$doppler->get_command(options);
```

## Options
| Option                    | Description                                                                                              | Default Value               |
|---------------------------|----------------------------------------------------------------------------------------------------------|-----------------------------|
| `page_start_at`           | Starting page number for PDF conversion.                                                                 | `0`                         |
| `batch_size`              | Number of pages to process in each batch during batch processing. Set to `0` for single-page processing. | `0`                         |
| `resolution`              | Resolution (dots per inch) of the generated images.                                                      | `300`                       |
| `compression_quality`     | Compression quality for JPEG images (applies only to JPEG output).                                       | `100`                       |
| `alpha_bits`              | Number of bits for alpha channel transparency in PNG images.                                             | `4`                         |
| `disable_color_management`| Disable color management during PDF conversion.                                                          | `true`                      |
| `disable_font`            | Disable font inclusion in the generated images.                                                          | `true`                      |
| `disable_annotations`     | Disable annotations in the PDF during conversion.                                                        | `true`                      |


### Additional Notes

- The example assumes that the Ghostscript and QPDF executables are in the system's PATH.
- Make sure the web server has the necessary permissions to read the input PDF file and write to the output directory.
- Ensure that the `proc_open` function is not disabled in your PHP configuration. Check the `disable_functions` directive in your `php.ini` file and remove `proc_open` if present.
