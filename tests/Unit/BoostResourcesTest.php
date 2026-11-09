<?php

declare(strict_types=1);

const BOOST_PATH = __DIR__.'/../../resources/boost';

it('has the boost guideline', function () {
    expect(BOOST_PATH.'/guidelines/core.blade.php')->toBeReadableFile();
});

it('has valid skill frontmatter', function (string $skillFile) {
    preg_match('/\A---\n(.*?)\n---\n/s', (string) file_get_contents($skillFile), $matches);

    expect($matches)->not->toBeEmpty('Missing frontmatter in '.$skillFile);

    preg_match('/^name: (.+)$/m', $matches[1], $name);
    preg_match('/^description: (.+)$/m', $matches[1], $description);

    expect($name[1] ?? null)->toBe(basename(dirname($skillFile)))
        ->and(strlen($description[1] ?? ''))->toBeGreaterThan(0)->toBeLessThanOrEqual(1024);
})->with(fn () => glob(BOOST_PATH.'/skills/*/SKILL.md'));

it('only links to existing files in skills', function (string $file) {
    preg_match_all('/\]\((?!https?:)([^)#]+)(?:#[^)]*)?\)/', (string) file_get_contents($file), $links);

    expect($file)->toBeReadableFile();

    foreach ($links[1] as $link) {
        expect(dirname($file).'/'.$link)->toBeReadableFile();
    }
})->with(fn () => [
    ...glob(BOOST_PATH.'/skills/*/SKILL.md'),
    ...glob(BOOST_PATH.'/skills/*/references/*.md'),
]);

it('only links to existing files and anchors in the docs', function (string $file) {
    $content = (string) file_get_contents($file);
    // `../../contributors` is a link of GitHub, not a file.
    preg_match_all('/\]\((?!https?:|mailto:|\.\.\/)([^)#]*)(?:#([^)]*))?\)/', $content, $links, PREG_SET_ORDER);

    expect($links)->not->toBeEmpty();

    foreach ($links as $link) {
        $target = '' === $link[1] ? $file : dirname($file).'/'.$link[1];

        expect($target)->toBeReadableFile();

        if (isset($link[2]) && '' !== $link[2] && str_ends_with($target, '.md')) {
            preg_match_all('/^#+ (.+)$/m', (string) file_get_contents($target), $headings);
            $anchors = array_map(
                fn (string $heading) => str_replace(' ', '-', trim((string) preg_replace('/[^a-z0-9 -]/', '', strtolower($heading)))), // like GitHub
                $headings[1],
            );

            expect($anchors)->toContain($link[2]);
        }
    }
})->with(fn () => [__DIR__.'/../../README.md', __DIR__.'/../../UPGRADING.md', __DIR__.'/../../CHANGELOG.md']);
