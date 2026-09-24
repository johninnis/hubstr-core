<?php

declare(strict_types=1);

use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use Amp\Socket\ResourceServerSocketFactory;
use Innis\Hubstr\Core\Application\Service\Kernel;
use Innis\Hubstr\Core\Domain\Enum\HttpMethod;
use Innis\Hubstr\Core\Domain\ValueObject\ServiceRuntimeConfig;
use Innis\Hubstr\Core\Domain\ValueObject\SiteInfo;
use Innis\Hubstr\Core\Infrastructure\Config\ConfigLoader;
use Innis\Hubstr\Core\Infrastructure\Http\HttpServerFactory;
use Innis\Hubstr\Core\Infrastructure\Http\HttpServerOptions;
use Innis\Hubstr\Core\Infrastructure\Http\Route;
use Innis\Hubstr\Core\Infrastructure\Http\RouterDefinition;
use Innis\Hubstr\Core\Infrastructure\Http\StaticSiteInfoProvider;
use Innis\Hubstr\Core\Infrastructure\Logging\LoggerFactory;
use Innis\Hubstr\Core\Infrastructure\Persistence\SchemaMigrator;
use Innis\Hubstr\Core\Infrastructure\Persistence\SqliteDatabase;
use Innis\Hubstr\Core\Infrastructure\Process\AmphpShutdownSignal;
use Innis\Hubstr\Core\Infrastructure\Templating\LatteTemplateRenderer;
use Innis\Hubstr\Core\Infrastructure\Version\ComposerVersionProvider;
use Innis\Hubstr\Core\Presentation\Http\ErrorPageResponder;
use Innis\Hubstr\Core\Presentation\Http\LandingPageResponder;
use Innis\Hubstr\Core\Presentation\Http\TemplatedErrorHandler;

use function Amp\ByteStream\getStdout;

require __DIR__.'/../vendor/autoload.php';

$values = new ConfigLoader('HUBSTR_CORE_EXAMPLE_CONFIG')->load(__DIR__.'/config/site.php');
$values->rejectUnknownKeys('site_name', 'owner_npub', 'template_cache_path', ...ServiceRuntimeConfig::KEYS);
$runtime = ServiceRuntimeConfig::fromValues($values);
$binding = $runtime->getBinding();

$logger = new LoggerFactory(getStdout(), $runtime->getLogLevel())->create('example');

$database = SqliteDatabase::atPath($runtime->getDatabasePath())->connect();
new SchemaMigrator($database)->migrate(__DIR__.'/resources/migrations');
$database->exec("INSERT INTO starts (started_at) VALUES (strftime('%s', 'now'))");

$renderer = LatteTemplateRenderer::create(__DIR__.'/templates', $values->string('template_cache_path'));
$site = new StaticSiteInfoProvider(new SiteInfo($values->string('site_name'), new ComposerVersionProvider()->getVersion(), $values->optionalString('owner_npub')));

$landingPage = new LandingPageResponder('index.latte', $renderer, $site);
$errorHandler = new TemplatedErrorHandler(new ErrorPageResponder('error.latte', $renderer, $site));

$definition = new RouterDefinition(
    [new Route(HttpMethod::Get, '/', static fn (Request $request): Response => $landingPage->respond())],
    $errorHandler,
    __DIR__.'/public',
);

$factory = new HttpServerFactory($logger, new ResourceServerSocketFactory());
$socketServer = $factory->createSocketServer($binding, HttpServerOptions::create(concurrencyLimit: 64));
$server = $factory->createServer($socketServer, $definition);

new Kernel($logger, new AmphpShutdownSignal())->run($server, 'Serving the example site', [
    'url' => sprintf('http://%s:%d', $binding->getHost(), $binding->getPort()),
    'database' => $runtime->getDatabasePath(),
    'start' => $database->lastInsertId(),
]);
