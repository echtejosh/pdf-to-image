# Doppler 

Doppler is a PHP class that provides a convenient interface for converting PDF files to images using Ghostscript. It supports both single-page and batch processing, allowing for customization of various parameters.

## Installation

Before using Doppler, ensure that you have Ghostscript and QPDF installed on your system. You can install them using the following commands:

```bash
sudo apt-get install ghostscript
sudo apt-get install qpdf
```

## Example
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

## Options

| Option                    | Default               | Description                                                                                              |
|---------------------------|-----------------------------|----------------------------------------------------------------------------------------------------------|
| `page_start_at`           | `0`                         | Starting page number for PDF conversion.                                                                 |
| `batch_size`              | `0`                         | Number of pages to process in each batch during batch processing. Set to `0` for single-page processing. |
| `resolution`              | `300`                       | Resolution (dots per inch) of the generated images.                                                      |
| `compression_quality`     | `100`                       | Compression quality for JPEG images (applies only to JPEG output).                                       |
| `alpha_bits`              | `4`                         | Number of bits for alpha channel transparency in PNG images.                                             |
| `disable_color_management`| `true`                      | Disable color management during PDF conversion.                                                          |
| `disable_font`            | `true`                      | Disable font inclusion in the generated images.                                                          |
| `disable_annotations`     | `true`                      | Disable annotations in the PDF during conversion.                                                        |

## Functions

### read(path)
Set a PDF to be read and converted. Referenced as `path`:
```php
$doppler->read(path);
```
- path: relative or real path to the PDF file.

### configure(options)
Configure the conversion process for the PDF:
```php
$doppler->configure(options);
```
- options: see `options` above.

### process(directory, type)
Convert to `type` which can either be JPG or PNG the given directory:
```php
$doppler->process(directory, type);
```
- directory: the directory in which the resulting conversions should be outputted.
- type: specifies the file format for the conversion, either JPG or PNG.

### get_command()
Get the Ghostscript command for processing the PDF file:
```php
$doppler->get_command(options);
```
- options: see `options` above.

## Additional Notes

- The example assumes that the Ghostscript and QPDF executables are in the system's PATH.
- Make sure the web server has the necessary permissions to read the input PDF file and write to the output directory.
- Ensure that the `proc_open` function is not disabled in your PHP configuration. Check the `disable_functions` directive in your `php.ini` file and remove `proc_open` if present.
