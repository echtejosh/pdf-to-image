<?php

/**
 * Class PdfToImage (PDF to Image)
 *
 * Represents a tool for converting PDF files to images using Ghostscript.
 */
class PdfToImage
{
    /**
     * Path to Ghostscript executable.
     *
     * @var string
     */
    private string $path;

    /**
     * Path to the PDF file.
     *
     * @var string
     */
    public string $filename;

    /**
     * Standard command parameters for Ghostscript.
     *
     * @var array<string>
     */
    public array $standard_command_parameters = [
        '-dNOPAUSE',
        '-dBATCH',
        '-dNumRenderingThreads=4',
        '-dBufferSpace=1000000000',
        '-dBandBufferSpace=500000000',
        '-dMaxBitmap=10000000',
        '-dNOGC'
    ];

    /**
     * Standard configuration for Ghostscript.
     *
     * @var array<string, int|bool|string>
     */
    public array $standard_configuration = [
        'resolution' => 300,
        'compression_quality' => 100,
        'alpha_bits' => 4,
        'disable_color_management' => true,
        'disable_font' => true,
        'disable_annotations' => true,
    ];

    /**
     * Configuration set by the user.
     *
     * @var array<string, int|bool|string>
     */
    public array $user_configuration = [];

    /**
     * Current command parameters set by the user.
     *
     * @var array
     */
    public array $user_command_parameters = [];

    /**
     * Set the path of Ghostscript executable.
     *
     * @param string $path The path to Ghostscript executable.
     * @return $this
     */
    public function set_path(string $path): PdfToImage
    {
        $this->path = $path;

        return $this;
    }

    /**
     * Process command in terminal.
     *
     * @param string $command The command to be executed in the terminal.
     * @return void
     */
    private function process_command(string $command): void
    {
        $process = proc_open(
            $command,
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes
        );

        if (is_resource($process) === false) {
            echo 'Error opening process for single-page processing';

            return;
        }

        $stderr = stream_get_contents($pipes[2]);

        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        if ($process && proc_close($process)) {
            echo 'Error during process: ' . $stderr;
        }
    }

    /**
     * Read PDF file.
     *
     * @param string $path The path to the PDF file.
     * @return $this
     * @throws Exception If the file is not found.
     */
    public function read(string $path): PdfToImage
    {
        $filepath = realpath($path);

        if (
            $filepath === false ||
            file_exists($filepath) === false
        ) {
            throw new RuntimeException('File not found');
        }

        $this->filename = $filepath;

        return $this;
    }

    /**
     * Get the number of pages in a PDF file.
     *
     * @param string $path The path to the PDF file.
     * @return string|null The number of pages in the PDF file.
     */
    public function get_page_amount(string $path): ?string
    {
        if (isset($this->path) === false) {
            throw new Error('Path is not set');
        }

        return shell_exec($this->path . ' -q --permit-file-read=$path -dNODISPLAY -c ' . '"(' . $path . ') (r) file runpdfbegin pdfpagecount = quit"');
    }

    /**
     * Set user configuration for Ghostscript.
     *
     * @param array $configuration The user configuration settings.
     * @return $this
     */
    public function set_configuration(array $configuration): PdfToImage
    {
        $this->user_configuration = $configuration;

        return $this;
    }

    /**
     * Add parameters to the current command parameters.
     *
     * @param array $parameters The parameters to add.
     * @return void
     */
    private function add_parameters(array $parameters): void
    {
        $this->user_command_parameters = array_merge($this->user_command_parameters, $parameters);
    }

    /**
     * Get the value of a specific parameter from the current command parameters.
     *
     * @param string $parameter The parameter to retrieve.
     * @return mixed|null The value of the parameter if found, null otherwise.
     */
    private function get_parameter(string $parameter)
    {
        $values = array_flip($this->user_command_parameters);

        return isset($values[$parameter]) ? $this->user_command_parameters[$values[$parameter]] : null;
    }

    /**
     * Get the value of a specific configuration setting.
     *
     * @param string $id The ID of the configuration setting.
     * @return int|mixed|string|null The value of the configuration setting, or null if not found.
     */
    private function get_configuration_value(string $id)
    {
        return array_merge($this->standard_configuration, $this->user_configuration)[$id] ?? null;
    }

    /**
     * Get all parameters (standard and user-defined) for the command.
     *
     * @return array The combined parameters.
     */
    private function get_parameters(): array
    {
        return array_merge($this->standard_command_parameters, $this->user_command_parameters);
    }

    /**
     * Get the full command string to execute.
     *
     * @param array|null $parameters Additional parameters to include (optional).
     * @return string The command string.
     *
     * @throws Error If the path is not set.
     */
    public function get_command(array $parameters = null): string
    {
        $command_parameters = implode(' ', $parameters ?? $this->get_parameters());

        if (isset($this->path) === false) {
            throw new Error('Path is not set');
        }

        return str_replace(PHP_EOL, ' ', $this->path . ' ' . $command_parameters);
    }

    /**
     * Process the conversion of the PDF file to images.
     *
     * @param string $directory The directory to save the converted images.
     * @param string $type The type of image to generate (default is 'jpg').
     * @return void
     * @throws RuntimeException If the directory is invalid or the image type is not supported.
     *
     * @throws RuntimeException If provided directory cannot be found or is not a directory.
     * @throws Error If there is no file being read.
     */
    public function process(string $directory, string $type = 'jpg'): void
    {
        if (!is_dir($directory)) {
            throw new RuntimeException('Invalid directory: ' . $directory);
        }

        $types = [
            'png' => 'pngalpha',
            'jpg' => 'jpeg'
        ];

        if (!isset($types[$type])) {
            throw new RuntimeException('This type is not supported');
        }

        $this->add_parameters(
            array_merge(
                $this->standard_command_parameters,
                [
                    '-r' . $this->get_configuration_value('resolution'),
                    '-sDEVICE=' . $types[$type]
                ]
            )
        );

        if ($this->get_parameter('-sDEVICE=jpeg')) {
            $this->add_parameters(
                [
                    '-dJPEGQ=' . $this->get_configuration_value('compression_quality'),
                    '-dCOLORSCREEN'
                ]
            );
        }

        if ($this->get_parameter('-sDEVICE=pngalpha')) {
            $alpha_bits = $this->get_configuration_value('alpha_bits');

            $this->add_parameters(
                [
                    '-dGraphicsAlphaBits=' . $alpha_bits,
                    '-dTextAlphaBits=' . $alpha_bits
                ]
            );
        }

        $start = microtime(true);

        if ($this->get_configuration_value('disable_color_management')) {
            $this->add_parameters(
                ['-dColorConversionStrategy=/LeaveColorUnchanged']
            );
        }

        if ($this->get_configuration_value('disable_font')) {
            $this->add_parameters(
                ['-dNOFONT']
            );
        }

        if ($this->get_configuration_value('disable_annotations')) {
            $this->add_parameters(
                ['-dPrinted']
            );
        }

        if (!isset($this->filename)) {
            throw new Error('There is no file being read');
        }

        $this->add_parameters(
            [
                '-dFirstPage=1',
                '-dLastPage=' . $this->get_page_amount($this->filename),
                '-sOutputFile=' . $directory . 'page_%d.' . $type,
                $this->filename
            ]
        );

        $this->process_command($this->get_command());

        $end = microtime(true);
        $total = $end - $start;

        echo 'Total processing time: ' . $total . ' seconds';
    }
}
