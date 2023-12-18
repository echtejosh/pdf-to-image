<?php

class Doppler
{
    public $file_name;

    // use ghostscript docs
    public $default_parameters = [
        '-dNOPAUSE',
        '-dBATCH',
        '-dNumRenderingThreads=12', // change to amount of cores u want to use
        '-dBufferSpace=1000000000',
        '-dBandBufferSpace=500000000',
        '-dNOTRANSPARENCY',
        '-dNOGC'
    ];

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

    public $config = [];

    public $parameters = [];

    private function run_process($command)
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

    public function read($file_name): Doppler
    {
        $file_path = realpath($file_name);

        if ($file_path === false || !file_exists($file_path)) {
            throw new Exception('file not found, is this path correct?');
        }

        $this->file_name = $file_path;

        return $this;
    }

    private function get_page_count($file_name)
    {
        return shell_exec('qpdf --show-npages ' . $file_name);
    }

    public function configure($config): Doppler
    {
        $this->config = $config;

        return $this;
    }

    private function add_params($params)
    {
        $this->parameters = array_merge($this->parameters, $params);
    }

    private function get_parameter($param)
    {
        $_params = array_flip($this->parameters);

        return isset($_params[$param]) ? $this->parameters[$_params[$param]] : null;
    }

    private function get_config_var($var)
    {
        return array_merge($this->default_config, $this->config)[$var] ?? null;
    }

    private function get_parameters()
    {
        return array_merge($this->default_parameters, $this->parameters);
    }

    public function get_command($params = null): string
    {
        return str_replace(["\n", "\r", '  '], ' ', 'gs ' . join(' ', $params ?? $this->get_parameters()));
    }

    public function process($path, $type = 'jpg')
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

        // init default params
        $this->add_params(
            array_merge(
                $this->default_parameters,
                [
                    '-r' . $this->get_config_var('resolution'),
                    '-sDEVICE=' . $_types[$type]
                ]
            )
        );

        // jpg
        if ($this->get_parameter('-sDEVICE=jpeg')) {
            $this->add_params(
                [
                    '-dJPEGQ=' . $this->get_config_var('compression_quality'),
                    '-dCOLORSCREEN'
                ]
            );
        }

        // png
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

        // ignore batch processing
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

        // batch processing
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

$doppler = new Doppler();

// test example of usage
$doppler
    ->read('pdf_test_30min.pdf')
    ->configure([
        'resolution' => 250,
        'compression_quality' => 80,
    ])
    ->process('images/');