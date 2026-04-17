<?php

declare(strict_types=1);

namespace RKD\LlmsTxt\Test\Unit\Controller;

use Magento\Framework\App\Action\Forward;
use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Url;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RKD\LlmsTxt\Controller\Router;
use RKD\LlmsTxt\Model\Config;

class RouterTest extends TestCase
{
    private ActionFactory&MockObject $actionFactoryMock;
    private Config&MockObject $configMock;
    private \Magento\Framework\App\Request\Http&MockObject $requestMock;
    private Router $router;

    protected function setUp(): void
    {
        $this->actionFactoryMock = $this->createMock(ActionFactory::class);
        $this->configMock = $this->createMock(Config::class);
        $this->requestMock = $this->getMockBuilder(\Magento\Framework\App\Request\Http::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->router = new Router(
            $this->actionFactoryMock,
            $this->configMock
        );
    }

    public function testMatchLlmsTxtReturnsForwardAction(): void
    {
        $forwardAction = $this->createMock(ActionInterface::class);

        $this->requestMock->method('getModuleName')->willReturn(null);
        $this->requestMock->method('getPathInfo')->willReturn('/llms.txt');
        $this->configMock->method('isEnabled')->willReturn(true);

        $this->requestMock->method('setModuleName')->willReturnSelf();
        $this->requestMock->method('setControllerName')->willReturnSelf();
        $this->requestMock->method('setActionName')->willReturnSelf();
        $this->requestMock->method('setParam')->willReturnSelf();
        $this->requestMock->method('setAlias')->willReturnSelf();

        $this->actionFactoryMock
            ->expects($this->once())
            ->method('create')
            ->with(Forward::class)
            ->willReturn($forwardAction);

        $result = $this->router->match($this->requestMock);

        $this->assertSame($forwardAction, $result);
    }

    public function testMatchLlmsFullTxtReturnsForwardAction(): void
    {
        $forwardAction = $this->createMock(ActionInterface::class);

        $this->requestMock->method('getModuleName')->willReturn(null);
        $this->requestMock->method('getPathInfo')->willReturn('/llms-full.txt');
        $this->configMock->method('isEnabled')->willReturn(true);

        $this->requestMock->method('setModuleName')->willReturnSelf();
        $this->requestMock->method('setControllerName')->willReturnSelf();
        $this->requestMock->method('setActionName')->willReturnSelf();
        $this->requestMock->method('setParam')->willReturnSelf();
        $this->requestMock->method('setAlias')->willReturnSelf();

        $this->actionFactoryMock
            ->expects($this->once())
            ->method('create')
            ->with(Forward::class)
            ->willReturn($forwardAction);

        $result = $this->router->match($this->requestMock);

        $this->assertSame($forwardAction, $result);
    }

    /**
     * @dataProvider nonMatchingPathProvider
     */
    public function testReturnsNullForNonMatchingPaths(string $path): void
    {
        $this->requestMock->method('getModuleName')->willReturn(null);
        $this->requestMock->method('getPathInfo')->willReturn($path);

        $this->actionFactoryMock->expects($this->never())->method('create');

        $result = $this->router->match($this->requestMock);

        $this->assertNull($result);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonMatchingPathProvider(): array
    {
        return [
            'about-us page' => ['/about-us'],
            'products page' => ['/products'],
            'hyphenated llms-txt (not a dot)' => ['/llms-txt'],
            'catalog category' => ['/catalog/category/view'],
            'nested path with llms' => ['/some/llms.txt/path'],
        ];
    }

    public function testReturnsNullWhenModuleIsDisabled(): void
    {
        $this->requestMock->method('getModuleName')->willReturn(null);
        $this->requestMock->method('getPathInfo')->willReturn('/llms.txt');
        $this->configMock->method('isEnabled')->willReturn(false);

        $this->actionFactoryMock->expects($this->never())->method('create');

        $result = $this->router->match($this->requestMock);

        $this->assertNull($result);
    }

    public function testSetsCorrectModuleControllerActionForLlmsTxt(): void
    {
        $forwardAction = $this->createMock(ActionInterface::class);

        $this->requestMock->method('getModuleName')->willReturn(null);
        $this->requestMock->method('getPathInfo')->willReturn('/llms.txt');
        $this->configMock->method('isEnabled')->willReturn(true);

        $this->requestMock->expects($this->once())->method('setModuleName')->with('rkd_llmstxt')->willReturnSelf();
        $this->requestMock->expects($this->once())->method('setControllerName')->with('index')->willReturnSelf();
        $this->requestMock->expects($this->once())->method('setActionName')->with('serve')->willReturnSelf();
        $this->requestMock->method('setParam')->willReturnSelf();
        $this->requestMock->method('setAlias')->willReturnSelf();

        $this->actionFactoryMock->method('create')->willReturn($forwardAction);

        $this->router->match($this->requestMock);
    }

    public function testSetsFileTypeParamLlmsTxtForLlmsTxtPath(): void
    {
        $forwardAction = $this->createMock(ActionInterface::class);

        $this->requestMock->method('getModuleName')->willReturn(null);
        $this->requestMock->method('getPathInfo')->willReturn('/llms.txt');
        $this->configMock->method('isEnabled')->willReturn(true);

        $this->requestMock->method('setModuleName')->willReturnSelf();
        $this->requestMock->method('setControllerName')->willReturnSelf();
        $this->requestMock->method('setActionName')->willReturnSelf();
        $this->requestMock->expects($this->once())->method('setParam')->with('file_type', 'llms_txt')->willReturnSelf();
        $this->requestMock->method('setAlias')->willReturnSelf();

        $this->actionFactoryMock->method('create')->willReturn($forwardAction);

        $this->router->match($this->requestMock);
    }

    public function testSetsFileTypeParamLlmsFullTxtForLlmsFullTxtPath(): void
    {
        $forwardAction = $this->createMock(ActionInterface::class);

        $this->requestMock->method('getModuleName')->willReturn(null);
        $this->requestMock->method('getPathInfo')->willReturn('/llms-full.txt');
        $this->configMock->method('isEnabled')->willReturn(true);

        $this->requestMock->method('setModuleName')->willReturnSelf();
        $this->requestMock->method('setControllerName')->willReturnSelf();
        $this->requestMock->method('setActionName')->willReturnSelf();
        $this->requestMock->expects($this->once())->method('setParam')->with('file_type', 'llms_full_txt')->willReturnSelf();
        $this->requestMock->method('setAlias')->willReturnSelf();

        $this->actionFactoryMock->method('create')->willReturn($forwardAction);

        $this->router->match($this->requestMock);
    }

    public function testReturnsNullWhenModuleNameAlreadySetToRkdLlmstxt(): void
    {
        $this->requestMock->method('getModuleName')->willReturn('rkd_llmstxt');
        $this->requestMock->method('getPathInfo')->willReturn('/llms.txt');

        $this->configMock->expects($this->never())->method('isEnabled');
        $this->actionFactoryMock->expects($this->never())->method('create');

        $result = $this->router->match($this->requestMock);

        $this->assertNull($result);
    }

    public function testHandlesEmptyPathInfo(): void
    {
        $this->requestMock->method('getModuleName')->willReturn(null);
        $this->requestMock->method('getPathInfo')->willReturn('');

        $this->actionFactoryMock->expects($this->never())->method('create');

        $result = $this->router->match($this->requestMock);

        $this->assertNull($result);
    }

    public function testHandlesNullPathInfo(): void
    {
        $this->requestMock->method('getModuleName')->willReturn(null);
        $this->requestMock->method('getPathInfo')->willReturn(null);

        $this->actionFactoryMock->expects($this->never())->method('create');

        $result = $this->router->match($this->requestMock);

        $this->assertNull($result);
    }

    public function testSetsRewriteRequestPathAlias(): void
    {
        $forwardAction = $this->createMock(ActionInterface::class);

        $this->requestMock->method('getModuleName')->willReturn(null);
        $this->requestMock->method('getPathInfo')->willReturn('/llms.txt');
        $this->configMock->method('isEnabled')->willReturn(true);

        $this->requestMock->method('setModuleName')->willReturnSelf();
        $this->requestMock->method('setControllerName')->willReturnSelf();
        $this->requestMock->method('setActionName')->willReturnSelf();
        $this->requestMock->method('setParam')->willReturnSelf();
        $this->requestMock
            ->expects($this->once())
            ->method('setAlias')
            ->with(Url::REWRITE_REQUEST_PATH_ALIAS, 'llms.txt')
            ->willReturnSelf();

        $this->actionFactoryMock->method('create')->willReturn($forwardAction);

        $this->router->match($this->requestMock);
    }
}
