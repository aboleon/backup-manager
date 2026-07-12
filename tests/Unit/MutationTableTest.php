<?php

declare(strict_types=1);

namespace Aboleon\BackupManager\Tests\Unit;

use Aboleon\BackupManager\Tracking\MutationTable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MutationTableTest extends TestCase
{
    #[DataProvider('mutations')]
    public function test_it_extracts_the_mutated_table(string $sql, ?string $expected): void
    {
        $this->assertSame($expected, (new MutationTable)->fromSql($sql));
    }

    public static function mutations(): array
    {
        return [
            ['insert into `articles` (`title`) values (?)', 'articles'],
            ['insert into `tenant`.`articles` (`title`) values (?)', 'articles'],
            ['insert into "tenant"."articles" ("title") values (?)', 'articles'],
            ['insert into [tenant].[articles] ([title]) values (?)', 'articles'],
            ['update "articles" set "title" = ?', 'articles'],
            ['delete from [articles] where [id] = ?', 'articles'],
            ['alter table `articles` add `slug` varchar(255)', 'articles'],
            ['create table if not exists `articles` (`id` integer)', 'articles'],
            ["/* migration */\nupdate `articles` set `title` = ?", 'articles'],
            ['with selected as (select id from sources) update articles set title = ?', 'articles'],
            ["load data infile 'articles.csv' into table articles", 'articles'],
            ['select * from `articles`', null],
        ];
    }

    public function test_it_distinguishes_mutations_from_reads(): void
    {
        $mutation = new MutationTable;

        $this->assertTrue($mutation->isMutation('call refresh_content()'));
        $this->assertTrue($mutation->isMutation('with selected as (select id from sources) delete from articles'));
        $this->assertFalse($mutation->isMutation('select * from articles'));
        $this->assertFalse($mutation->isMutation('with selected as (select id from sources) select * from selected'));
    }
}
