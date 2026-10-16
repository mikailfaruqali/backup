<?php

declare(strict_types=1);

namespace Snawbar\Backup\Dumpers;

use Illuminate\Support\Facades\File;
use Spatie\DbDumper\Databases\PostgreSql as SpatiePostgreSql;
use SplFileObject;

class PostgreSql extends SpatiePostgreSql
{
    private const HEADER_END_PREFIX = '-- Name: ';

    private const RESTRICT_PREFIX = '\restrict ';

    private const UNRESTRICT_FORMAT = '\unrestrict %s';

    private const VERSION_SPECIFIC_SETTINGS = [
        'SET transaction_timeout = 0;',
        'SET default_table_access_method = heap;',
    ];

    private bool $inHeader = TRUE;

    private ?string $restrictKey = NULL;

    public function __construct()
    {
        parent::__construct();

        $this->useInserts()
            ->addExtraOption('--no-owner')
            ->addExtraOption('--no-privileges');
    }

    public function dumpToFile(string $dumpFile): void
    {
        parent::dumpToFile($dumpFile);

        $this->makePortable(dumpFile: $dumpFile);
    }

    private function makePortable(string $dumpFile): void
    {
        $portableFile = sprintf('%s.portable', $dumpFile);

        $this->copyPortableLines(source: $dumpFile, destination: $portableFile);

        File::move($portableFile, $dumpFile);
    }

    private function copyPortableLines(string $source, string $destination): void
    {
        $input = new SplFileObject($source);
        $output = new SplFileObject($destination, 'w');

        $this->inHeader = TRUE;
        $this->restrictKey = NULL;

        foreach ($input as $line) {
            if ($this->shouldKeep(line: (string) $line)) {
                $output->fwrite((string) $line);
            }
        }
    }

    private function shouldKeep(string $line): bool
    {
        $statement = rtrim($line, "\r\n");

        $this->trackHeader(statement: $statement);

        return match (TRUE) {
            $this->isRestrictCommand(statement: $statement) => $this->dropRestrictCommand(statement: $statement),
            $this->isVersionSpecificSetting(statement: $statement) => FALSE,
            $this->isUnrestrictCommand(statement: $statement) => FALSE,
            default => TRUE,
        };
    }

    private function trackHeader(string $statement): void
    {
        $this->inHeader = $this->inHeader && ! str_starts_with($statement, self::HEADER_END_PREFIX);
    }

    private function isRestrictCommand(string $statement): bool
    {
        return $this->inHeader && str_starts_with($statement, self::RESTRICT_PREFIX);
    }

    private function dropRestrictCommand(string $statement): bool
    {
        $this->restrictKey = substr($statement, strlen(self::RESTRICT_PREFIX));

        return FALSE;
    }

    private function isVersionSpecificSetting(string $statement): bool
    {
        return $this->inHeader && in_array($statement, self::VERSION_SPECIFIC_SETTINGS, TRUE);
    }

    private function isUnrestrictCommand(string $statement): bool
    {
        return filled($this->restrictKey) && $statement === sprintf(self::UNRESTRICT_FORMAT, $this->restrictKey);
    }
}
