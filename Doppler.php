<?php

class Doppler
{
    /**
     * Set path
     *
     * @var string
     */
    private string $ghostscript_path;

    /**
     * Set path of PDF
     *
     * @var string
     */
    public string $filename;

    /**
     * Standard command parameters
     *
     * @var array|string[]
     */
    public array $standard_command_parameters = [
        "-dNOPAUSE",
        "-dBATCH",
        "-dNumRenderingThreads=4",
        "-dBufferSpace=1000000000",
        "-dBandBufferSpace=500000000",
        "-dMaxBitmap=10000000",
        "-dNOGC"
    ];

    /**
     * Standard configuration
     *
     * @var array|int[]
     */
    public array $standard_configuration = [
        "resolution" => 300,
        "compression_quality" => 100,
        "alpha_bits" => 4,
        "disable_color_management" => true,
        "disable_font" => true,
        "disable_annotations" => true,
    ];

    /**
     * Configuration set by the user
     *
     * @var array
     */
    public array $user_configuration = [];

    /**
     * Current command parameters set by the user
     *
     * @var array
     */
    public array $user_command_parameters = [];

    /**
     * Set the path of path
     *
     * @param string $path
     * @return $this
     */
    public function set_ghostscript_path(string $path): Doppler
    {
        $this->ghostscript_path = $path;

        return $this;
    }

    /**
     * Process command in terminal
     *
     * @param string $command
     * @return void
     */
    private function process_command(string $command)
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
            echo "Error opening process for single-page processing";

            return;
        }

        $stderr = stream_get_contents($pipes[2]);

        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        if ($process && proc_close($process)) {
            echo "Error during process: $stderr";
        }
    }

    /**
     * Read PDF
     *
     * @param string $path
     * @return $this
     * @throws Exception
     */
    public function read(string $path): Doppler
    {
        $filepath = realpath($path);

        if (
            $filepath === false ||
            file_exists($filepath) === false
        ) {
            throw new Exception("File not found");
        }

        $this->filename = $filepath;

        return $this;
    }

    /**
     * Get PDF size in pages
     *
     * @param string $path
     * @return string|null
     */
    public function get_page_amount(string $path): ?string
    {
        if (isset($this->ghostscript_path) === false) {
            throw new Error("Path is not set");
        }

        return shell_exec("$this->ghostscript_path -q --permit-file-read=$path -dNODISPLAY -c " . '"(' . $path . ') (r) file runpdfbegin pdfpagecount = quit"');
    }

    /**
     * Set user configuration
     *
     * @param array $configuration
     * @return $this
     */
    public function configure(array $configuration): Doppler
    {
        $this->user_configuration = $configuration;

        return $this;
    }

    /**
     * Add parameters
     *
     * @param array $parameters
     * @return void
     */
    private function add_parameters(array $parameters)
    {
        $this->user_command_parameters = array_merge($this->user_command_parameters, $parameters);
    }

    /**
     * Get parameter
     *
     * @param string $parameters
     * @return mixed|null
     */
    private function get_parameter(string $parameters)
    {
        $values = array_flip($this->user_command_parameters);

        return isset($values[$parameters]) ? $this->user_command_parameters[$values[$parameters]] : null;
    }

    /**
     * Get value of a specific configuration
     *
     * @param string $id
     * @return int|mixed|string|null
     */
    private function get_config_value(string $id)
    {
        return array_merge($this->standard_configuration, $this->user_configuration)[$id] ?? null;
    }

    /**
     * Get parameters of user and standard
     *
     * @return array
     */
    private function get_parameters(): array
    {
        return array_merge($this->standard_command_parameters, $this->user_command_parameters);
    }

    /**
     * Get the command as string
     *
     * @param array|null $parameters
     * @return string
     */
    public function get_command(array $parameters = null): string
    {
        $command_parameters = join(" ", $parameters ?? $this->get_parameters());

        if (isset($this->ghostscript_path) === false) {
            throw new Error("Path is not set");
        }

        return str_replace(PHP_EOL, " ", "$this->ghostscript_path $command_parameters");
    }

    /**
     * Process the conversion
     *
     * @param string $directory
     * @param string $type
     * @return void
     * @throws Exception
     */
    public function process(string $directory, string $type = "jpg")
    {
        if (is_dir($directory) === false) {
            throw new Exception("Invalid directory:  $directory");
        }

        $types = [
            "png" => "pngalpha",
            "jpg" => "jpeg"
        ];

        if (isset($types[$type]) === false) {
            throw new Exception("This type is not supported");
        }

        $this->add_parameters(
            array_merge(
                $this->standard_command_parameters,
                [
                    "-r" . $this->get_config_value("resolution"),
                    "-sDEVICE=$types[$type]"
                ]
            )
        );

        if ($this->get_parameter("-sDEVICE=jpeg")) {
            $this->add_parameters(
                [
                    "-dJPEGQ=" . $this->get_config_value("compression_quality"),
                    "-dCOLORSCREEN"
                ]
            );
        }

        if ($this->get_parameter("-sDEVICE=pngalpha")) {
            $alpha_bits = $this->get_config_value("alpha_bits");

            $this->add_parameters(
                [
                    "-dGraphicsAlphaBits=$alpha_bits",
                    "-dTextAlphaBits=$alpha_bits"
                ]
            );
        }

        $start = microtime(true);

        if ($this->get_config_value("disable_color_management")) {
            $this->add_parameters(
                ["-dColorConversionStrategy=/LeaveColorUnchanged"]
            );
        }

        if ($this->get_config_value("disable_font")) {
            $this->add_parameters(
                ["-dNOFONT"]
            );
        }

        if ($this->get_config_value("disable_annotations")) {
            $this->add_parameters(
                ["-dPrinted"]
            );
        }

        if (isset($this->filename) === false) {
            throw new Error("There is no file being read");
        }

        $this->add_parameters(
            [
                "-dFirstPage=0",
                "-dLastPage=" . $this->get_page_amount($this->filename),
                "-sOutputFile={$directory}page_%d.$type",
                $this->filename
            ]
        );

        $this->process_command($this->get_command());

        $end = microtime(true);
        $total = $end - $start;

        echo "Total processing time: $total seconds";
    }
}