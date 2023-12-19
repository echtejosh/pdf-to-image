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
    public $gs_path;

    /**
     * The path to the PDF file to be processed.
     *
     * @var string $file_name
     */
    public $file_name;

    /**
     * Default Ghostscript parameters for PDF conversion.
     *
     * @var array $default_parameters
     */
    public $default_parameters = [
        '-dNOPAUSE',
        '-dBATCH',
        '-dNumRenderingThreads=12', // Change to amount of CPU cores u want to use.
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
    public $default_config = [
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
    public $config = [];

    /**
     * Additional Ghostscript parameters set by the user.
     *
     * @var array $parameters
     */
    public $parameters = [];

    /**
     * Set the path to the Ghostscript executable.
     *
     * @param string $path
     *
     * @return Doppler
     */
    public function set_interpreter(string $path): Doppler
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
    public function get_interpreter(): string
    {
        $interpreters = [
            $this->gs_path,
            'gswin64c',
            'gswin32c',
            'gs',
        ];

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
    private function run_process(string $command)
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
     * @param string $file_name
     *
     * @return Doppler
     * @throws Exception
     */
    public function read(string $file_name): Doppler
    {
        $file_path = realpath($file_name);

        if ($file_path === false || !file_exists($file_path)) {
            throw new Exception('file not found, is this path correct?');
        }

        $this->file_name = $file_path;

        return $this;
    }

    /**
     * Get the total number of pages in the PDF file.
     *
     * @param string $file_name
     *
     * @return string|null
     * @throws Exception
     */
    private function get_page_count(string $file_name): ?string
    {
        return shell_exec($this->get_interpreter() . ' -q --permit-file-read=./ -dNODISPLAY -c "(' . $file_name . ') (r) file runpdfbegin pdfpagecount = quit"');
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
     * @param array $params
     *
     * @return void
     */
    private function add_params(array $params)
    {
        $this->parameters = array_merge($this->parameters, $params);
    }

    /**
     * Get parameter.
     *
     * @param string $param
     *
     * @return mixed|null
     */
    private function get_parameter(string $param)
    {
        $_params = array_flip($this->parameters);

        return isset($_params[$param]) ? $this->parameters[$_params[$param]] : null;
    }

    /**
     * Get configuration.
     *
     * @param string $var
     *
     * @return int|mixed|null
     */
    private function get_config_var(string $var)
    {
        return array_merge($this->default_config, $this->config)[$var] ?? null;
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
     * @param array|null $params
     *
     * @return string
     * @throws Exception
     */
    public function get_command(array $params = null): string
    {
        return str_replace(["\n", "\r", '  '], ' ', $this->get_interpreter() . ' ' . join(' ', $params ?? $this->get_parameters()));
    }

    /**
     * Process the PDF file and generate images in the specified directory.
     *
     * @param string $path
     * @param string $type
     *
     * @throws Exception
     */
    public function process(string $path, string $type = 'jpg')
    {
        if (is_dir($path) === false) {
            throw new Exception('invalid directory: ' . $path);
        }

        $page_count = $this->get_page_count($this->file_name);

        $_types = [
            'png' => 'pngalpha',
            'jpg' => 'jpeg'
        ];

        if (isset($_types[$type]) === false) {
            throw new Exception('this type is not supported');
        }

        $this->add_params(
            array_merge(
                $this->default_parameters,
                [
                    '-r' . $this->get_config_var('resolution'),
                    '-sDEVICE=' . $_types[$type]
                ]
            )
        );

        // JPG
        if ($this->get_parameter('-sDEVICE=jpeg')) {
            $this->add_params(
                [
                    '-dJPEGQ=' . $this->get_config_var('compression_quality'),
                    '-dCOLORSCREEN'
                ]
            );
        }

        // PNG
        if ($this->get_parameter('-sDEVICE=pngalpha')) {
            $alpha_bits = $this->get_config_var('alpha_bits');

            $this->add_params(
                [
                    '-dGraphicsAlphaBits=' . $alpha_bits,
                    '-dTextAlphaBits=' . $alpha_bits
                ]
            );
        }

        $batch_size = $this->get_config_var('batch_size');
        $page_start_at = $this->get_config_var('page_start_at');

        $start = microtime(true);

        if ($this->get_config_var('disable_color_management')) {
            $this->add_params(
                ['-dColorConversionStrategy=/LeaveColorUnchanged']
            );
        }

        if ($this->get_config_var('disable_font')) {
            $this->add_params(
                ['-dNOFONT']
            );
        }

        if ($this->get_config_var('disable_annotations')) {
            $this->add_params(
                ['-dPrinted']
            );
        }

        // Ignore batch processing
        if ($batch_size === 0) {
            $this->add_params(
                [
                    '-dFirstPage=' . $page_start_at,
                    '-dLastPage=' . $page_count,
                    '-sOutputFile=' . $path . 'page_%d.' . $type,
                    $this->file_name
                ]
            );

            $this->run_process($this->get_command());

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
                        '-sOutputFile=' . $path . 'page_' . $batch . '_%d.' . $type,
                        $this->file_name
                    ]
                )
            );

            $this->run_process($command);

            $inner_end = microtime(true);
            $inner_total = $inner_end - $inner_start;

            echo "batch {$batch} processing time: {$inner_total} seconds\n";
        }

        $end = microtime(true);
        $total = $end - $start;

        echo "total processing time: {$total} seconds\n";
    }
}