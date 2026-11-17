<?php

declare(strict_types=1);

namespace Zactonz\Git;

use RuntimeException;

final class App
{
    public readonly Store $store;
    public readonly Credentials $credentials;
    public readonly ServerConfig $config;
    public readonly CommandRunner $runner;

    private function __construct(
        public readonly Paths $paths,
        public readonly string $pluginDir,
    ) {
        $this->config = ServerConfig::load();
        $this->store = new Store($paths);
        $this->credentials = new Credentials($paths, new Secrets($paths));
        $this->runner = new CommandRunner($this->config->deployTimeoutSeconds);
    }

    public static function forAccount(string $pluginDir): self
    {
        return new self(Paths::forCurrentAccount(), $pluginDir);
    }

    public static function forPaths(Paths $paths, string $pluginDir): self
    {
        return new self($paths, $pluginDir);
    }

    public function git(): ?GitBinary
    {
        static $binary = null;
        static $looked = false;

        if (!$looked) {
            $binary = GitBinary::locate($this->runner);
            $looked = true;
        }

        return $binary;
    }

    public function deployer(): Deployer
    {
        $git = $this->git();

        if ($git === null) {
            throw new RuntimeException('Git is not installed on this server, or is not in a location this plugin checks.');
        }

        return new Deployer(
            $this->paths,
            $this->store,
            $this->credentials,
            $this->config,
            $git,
            $this->runner,
            $this->pluginDir,
        );
    }

    public function log(string $repositoryId): DeployLog
    {
        return new DeployLog($this->paths, $repositoryId);
    }
}
