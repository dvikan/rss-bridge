<?php

final class RssBridge
{
    public function main(array $argv = [])
    {
        if ($argv) {
            parse_str(implode('&', array_slice($argv, 1)), $cliArgs);
            $request = $cliArgs;
        } else {
            $request = $_GET;
        }

        try {
            $this->run($request);
        } catch (\Throwable $e) {
            Logger::error('Exception in main', ['e' => $e]);
            http_response_code(500);
            print render('error.html.php', [
                'message' => create_sane_exception_message($e),
                'stacktrace' => create_sane_stacktrace($e),
            ]);
        }
    }

    private function run($request): void
    {
        $errors = check_installation_requirements();
        if ($errors) {
            throw new \Exception(sprintf('Configuration error: %s', implode(', ', $errors)));
        }
        $customConfig = [];
        if (file_exists(__DIR__ . '/../config.ini.php')) {
            $customConfig = parse_ini_file(__DIR__ . '/../config.ini.php', true, INI_SCANNER_TYPED);
        }
        $debug = __DIR__ . '/../DEBUG';
        if (file_exists($debug)) {
            $allowList = trim(file_get_contents($debug));
        }
        $ip = $_SERVER['REMOTE_ADDR'];
        Configuration::loadConfiguration(
            $customConfig,
            getenv(),
            $ip,
            $allowList ?? null
        );

        set_error_handler(function ($code, $message, $file, $line) {
            if ((error_reporting() & $code) === 0) {
                return false;
            }
            $text = sprintf('%s at %s line %s', $message, trim_path_prefix($file), $line);
            Logger::warning($text);
            if (Configuration::getConfig('system', 'debug')) {
                print sprintf('<pre>%s</pre>', $text);
            }
        });

        date_default_timezone_set(Configuration::getConfig('system', 'timezone'));

        $authenticationMiddleware = new AuthenticationMiddleware();
        if (Configuration::getConfig('authentication', 'enable')) {
            $authenticationMiddleware();
        }

        foreach ($request as $key => $value) {
            if (!is_string($value)) {
                throw new \Exception("Query parameter \"$key\" is not a string.");
            }
        }

        $actionFactory = new ActionFactory();
        $action = $request['action'] ?? 'Frontpage';
        $action = $actionFactory->create($action);
        $action->execute($request);
    }
}
