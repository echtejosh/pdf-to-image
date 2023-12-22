<?php

/**
 * Doppler
 *
 * This class provides a convenient interface for converting PDF files to images using Ghostscript.
 * It supports both single-page and batch processing.
 *
 * @author echtyushi
 * @version 1.0
 */
class Doppler
{
    /**
     * The path to the Ghostscript executable.
     *
     * @var string $gs_path
     */
    public string $gs_path = '';

    /**
     * The path to the PDF file to be processed.
     *
     * @var string $file_name
     */
    public string $file_name = '';

    /**
     * Default Ghostscript parameters for PDF conversion.
     *
     * @var array $default_parameters
     */
    public array $default_parameters = [
        '-dNOPAUSE',
        '-dBATCH',
        '-dNumRenderingThreads=4',
        '-dBufferSpace=1000000000',
        '-dBandBufferSpace=500000000',
        '-dNOTRANSPARENCY',
        '-dMaxBitmap=10000000',
        '-dNOGC'
    ];

    /**
     * Default configuration options for PDF conversion.
     *
     * @var array $default_config
     */
    public array $default_config = [
        'page_start_at' => 0,
        'batch_size' => 0,
        'resolution' => 300,
        'compression_quality' => 100,
        'alpha_bits' => 4,
        'disable_color_management' => true,
        'disable_font' => true,
        'disable_annotations' => true,
    ];

    /**
     * User-defined configuration options for PDF conversion.
     *
     * @var array $config
     */
    public array $config = [];

    /**
     * Additional Ghostscript parameters set by the user.
     *
     * @var array $parameters
     */
    public array $parameters = [];

    /**
     * Set the path to the Ghostscript executable.
     *
     * @param string $path
     *
     * @return Doppler
     */
    public function set_executable(string $path): Doppler
    {
        $this->gs_path = $path;

        return $this;
    }

    /**
     * Get the Ghostscript interpreter.
     *
     * @return string
     * @throws Exception
     */
    public function get_executable(): string
    {
        $interpreters = [
            'gswin64c',
            'gswin32c',
            'gs',
        ];

        $interpreters[] = $this->gs_path;

        foreach (array_filter($interpreters) as $interpreter) {
            exec($interpreter . ' --version', $output, $return_code);

            if ($return_code === 0) {
                return $interpreter;
            }
        }

        throw new Exception('ghostscript is not found, check if gs is installed or configured properly');
    }

    /**
     * Run a Ghostscript process with the given command.
     *
     * @param string $command
     */
    private function gs_proc(string $command)
    {
        $descriptors = [
            0 => ['pipe', 'r'], // stdin
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
        ];

        $process = proc_open($command, $descriptors, $pipes);

        if (is_resource($process)) {
            fclose($pipes[0]);

            $stderr = stream_get_contents($pipes[2]);

            fclose($pipes[1]);
            fclose($pipes[2]);

            $exit_code = proc_close($process);

            if ($exit_code !== 0) {
                echo "error during processing:\nexit code: {$exit_code}\nstandard error: {$stderr}\n";
            }
        } else {
            echo "error opening process for single-page processing\n";
        }
    }

    /**
     * Set the PDF file to be processed.
     *
     * @param string $path
     *
     * @return Doppler
     * @throws Exception
     */
    public function read(string $path): Doppler
    {
        $file_path = realpath($path);

        if (
            $file_path === false ||
            file_exists($file_path) === false
        ) {
            throw new Exception('file not found, is this path correct?');
        }

        $this->file_name = $file_path;

        return $this;
    }

    /**
     * Get the total number of pages in the PDF file.
     *
     * @param string $path
     *
     * @return string|null
     * @throws Exception
     */
    public function get_page_count(string $path): ?string
    {
        return shell_exec($this->get_executable() . ' -q --permit-file-read=' . $path . ' -dNODISPLAY -c "(' . $path . ') (r) file runpdfbegin pdfpagecount = quit"');
    }

    /**
     * Set user-defined configuration options for PDF conversion.
     *
     * @param array $config
     *
     * @return Doppler
     */
    public function configure(array $config): Doppler
    {
        $this->config = $config;

        return $this;
    }

    /**
     * Add parameters to the command.
     *
     * @param array $parameters
     *
     * @return void
     */
    private function add_parameters(array $parameters)
    {
        $this->parameters = array_merge($this->parameters, $parameters);
    }

    /**
     * Get parameter.
     *
     * @param string $parameters
     *
     * @return mixed|null
     */
    private function get_parameter(string $parameters)
    {
        $values = array_flip($this->parameters);

        return isset($values[$parameters]) ? $this->parameters[$values[$parameters]] : null;
    }

    /**
     * Get configuration.
     *
     * @param string $id
     *
     * @return int|mixed|null
     */
    private function get_config_value(string $id)
    {
        return array_merge($this->default_config, $this->config)[$id] ?? null;
    }

    /**
     * Get parameters.
     *
     * @return array
     */
    private function get_parameters(): array
    {
        return array_merge($this->default_parameters, $this->parameters);
    }

    /**
     * Get the Ghostscript command for processing the PDF file.
     *
     * @param array|null $parameters
     *
     * @return string
     * @throws Exception
     */
    public function get_command(array $parameters = null): string
    {
        return str_replace(["\n", "\r", '  '], ' ', $this->get_executable() . ' ' . join(' ', $parameters ?? $this->get_parameters()));
    }

    /**
     * Process the PDF file and generate images in the specified directory.
     *
     * @param string $directory
     * @param string $type
     *
     * @throws Exception
     */
    public function process(string $directory, string $type = 'jpg')
    {
        if (is_dir($directory) === false) {
            throw new Exception('invalid directory: ' . $directory);
        }

        $page_count = $this->get_page_count($this->file_name);

        $_types = [
            'png' => 'pngalpha',
            'jpg' => 'jpeg'
        ];

        if (isset($_types[$type]) === false) {
            throw new Exception('this type is not supported');
        }

        $this->add_parameters(
            array_merge(
                $this->default_parameters,
                [
                    '-r' . $this->get_config_value('resolution'),
                    '-sDEVICE=' . $_types[$type]
                ]
            )
        );

        // JPG
        if ($this->get_parameter('-sDEVICE=jpeg')) {
            $this->add_parameters(
                [
                    '-dJPEGQ=' . $this->get_config_value('compression_quality'),
                    '-dCOLORSCREEN'
                ]
            );
        }

        // PNG
        if ($this->get_parameter('-sDEVICE=pngalpha')) {
            $alpha_bits = $this->get_config_value('alpha_bits');

            $this->add_parameters(
                [
                    '-dGraphicsAlphaBits=' . $alpha_bits,
                    '-dTextAlphaBits=' . $alpha_bits
                ]
            );
        }

        $batch_size = $this->get_config_value('batch_size');
        $page_start_at = $this->get_config_value('page_start_at');

        $start = microtime(true);

        if ($this->get_config_value('disable_color_management')) {
            $this->add_parameters(
                ['-dColorConversionStrategy=/LeaveColorUnchanged']

            );
        }

        if ($this->get_config_value('disable_font')) {
            $this->add_parameters(
                ['-dNOFONT']
            );
        }

        if ($this->get_config_value('disable_annotations')) {
            $this->add_parameters(
                ['-dPrinted']
            );
        }

        // Ignore batch processing
        if ($batch_size === 0) {
            $this->add_parameters(
                [
                    '-dFirstPage=' . $page_start_at,
                    '-dLastPage=' . $page_count,
                    '-sOutputFile=' . $directory . 'page_%d.' . $type,
                    $this->file_name
                ]
            );

            $this->gs_proc($this->get_command());

            $end = microtime(true);
            $total = $end - $start;

            echo "total processing time: {$total} seconds\n";

            return;
        }

        // Batch processing
        for ($start_page = $page_start_at + 1, $batch = 1; $start_page <= $page_count; $start_page += $batch_size, $batch++) {
            $end_page = min($start_page + $batch_size - 1, $page_count);

            $inner_start = microtime(true);

            $command = $this->get_command(
                array_merge(
                    $this->get_parameters(),
                    [
                        '-dFirstPage=' . $start_page,
                        '-dLastPage=' . $end_page,
                        '-sOutputFile=' . $directory . 'page_' . $batch . '_%d.' . $type,
                        $this->file_name
                    ]
                )
            );

            $this->gs_proc($command);

            $inner_end = microtime(true);
            $inner_total = $inner_end - $inner_start;

            echo "batch {$batch} processing time: {$inner_total} seconds\n";
        }

        $end = microtime(true);
        $total = $end - $start;

        echo "total processing time: {$total} seconds\n";
    }
}