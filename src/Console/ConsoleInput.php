<?php
/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         2.0.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\Console;

/**
 * Object wrapper for interacting with stdin
 */
class ConsoleInput
{
    /**
     * Input value.
     *
     * @var resource
     */
    protected $_input;

    /**
     * Can this instance use readline?
     * Two conditions must be met:
     * 1. Readline support must be enabled.
     * 2. Handle we are attached to must be stdin.
     * Allows rich editing with arrow keys and history when inputting a string.
     *
     * @var bool
     */
    protected $_canReadline;

    /**
     * Constructor
     *
     * @param string $handle The location of the stream to use as input.
     */
    public function __construct($handle = 'php://stdin')
    {
        $this->_canReadline = (extension_loaded('readline') && $handle === 'php://stdin');
        $this->_input = fopen($handle, 'rb');
    }

    /**
     * Read a value from the stream
     *
     * @return mixed The value of the stream
     */
    public function read()
    {
        if ($this->_canReadline) {
            $line = readline('');
            if (strlen($line) > 0) {
                readline_add_history($line);
            }

            return $line;
        }

        return fgets($this->_input);
    }

    /**
     * Read a value from the stream
     *
     * @return mixed The value of the stream
     */
    public function readHidden()
    {
        if ($this->hasStty()) {
            shell_exec('stty -echo');
            $value = fgets($this->_input);
            shell_exec('stty echo');

            return $value;
        }

        if (DIRECTORY_SEPARATOR === '\\') {
            $exe = __DIR__ . '/resources/hiddeninput.exe';

            return shell_exec($exe);
        }

        $shell = $this->getShell();

        if ($shell) {
            $readCmd = 'csh' === $shell ? 'set mypassword = $<' : 'read -r mypassword';
            $command = sprintf("/usr/bin/env %s -c 'stty -echo; %s; stty echo; echo \$mypassword'", $shell, $readCmd);

            return shell_exec($command);
        }
    }


    /**
     * @return bool
     * @internal
     *
     */
    protected function hasStty()
    {
        exec('stty 2>&1', $output, $exitCode);

        return $exitCode === Command::CODE_SUCCESS;
    }

    /**
     * Returns a valid unix shell
     *
     * @return string|bool The valid shell name, false in case no valid shell is found
     */
    protected function getShell()
    {
        $shell = false;
        if (file_exists('/usr/bin/env')) {
            // handle other OSs with bash/zsh/ksh/csh if available to hide the answer
            $test = "/usr/bin/env %s -c 'echo OK' 2> /dev/null";
            foreach (['bash', 'zsh', 'ksh', 'csh'] as $sh) {
                if (rtrim(shell_exec(sprintf($test, $sh))) === 'OK') {
                    $shell = $sh;
                    break;
                }
            }
        }

        return $shell;
    }

    /**
     * Check if data is available on stdin
     *
     * @param int $timeout An optional time to wait for data
     * @return bool True for data available, false otherwise
     */
    public function dataAvailable($timeout = 0)
    {
        $readFds = [$this->_input];
        $writeFds = null;
        $errorFds = null;
        $readyFds = stream_select($readFds, $writeFds, $errorFds, $timeout);

        return ($readyFds > 0);
    }
}
