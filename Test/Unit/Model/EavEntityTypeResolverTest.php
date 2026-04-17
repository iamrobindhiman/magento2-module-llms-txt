<?php

declare(strict_types=1);

namespace RKD\LlmsTxt\Test\Unit\Model;

use Magento\Catalog\Api\Data\CategoryAttributeInterface;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Type as EntityType;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RKD\LlmsTxt\Model\EavEntityTypeResolver;

class EavEntityTypeResolverTest extends TestCase
{
    private EavConfig&MockObject $eavConfigMock;
    private EavEntityTypeResolver $resolver;

    protected function setUp(): void
    {
        $this->eavConfigMock = $this->createMock(EavConfig::class);
        $this->resolver = new EavEntityTypeResolver($this->eavConfigMock);
    }

    public function testGetProductEntityTypeIdReturnsCorrectId(): void
    {
        $entityTypeMock = $this->createMock(EntityType::class);
        $entityTypeMock->method('getEntityTypeId')->willReturn('4');

        $this->eavConfigMock
            ->method('getEntityType')
            ->with(ProductAttributeInterface::ENTITY_TYPE_CODE)
            ->willReturn($entityTypeMock);

        $result = $this->resolver->getProductEntityTypeId();

        $this->assertSame(4, $result);
    }

    public function testGetCategoryEntityTypeIdReturnsCorrectId(): void
    {
        $entityTypeMock = $this->createMock(EntityType::class);
        $entityTypeMock->method('getEntityTypeId')->willReturn('3');

        $this->eavConfigMock
            ->method('getEntityType')
            ->with(CategoryAttributeInterface::ENTITY_TYPE_CODE)
            ->willReturn($entityTypeMock);

        $result = $this->resolver->getCategoryEntityTypeId();

        $this->assertSame(3, $result);
    }

    public function testGetProductEntityTypeIdReturnsInteger(): void
    {
        $entityTypeMock = $this->createMock(EntityType::class);
        $entityTypeMock->method('getEntityTypeId')->willReturn('4');

        $this->eavConfigMock
            ->method('getEntityType')
            ->with(ProductAttributeInterface::ENTITY_TYPE_CODE)
            ->willReturn($entityTypeMock);

        $this->assertIsInt($this->resolver->getProductEntityTypeId());
    }

    public function testGetCategoryEntityTypeIdReturnsInteger(): void
    {
        $entityTypeMock = $this->createMock(EntityType::class);
        $entityTypeMock->method('getEntityTypeId')->willReturn('3');

        $this->eavConfigMock
            ->method('getEntityType')
            ->with(CategoryAttributeInterface::ENTITY_TYPE_CODE)
            ->willReturn($entityTypeMock);

        $this->assertIsInt($this->resolver->getCategoryEntityTypeId());
    }

    public function testProductEntityTypeIdIsCachedOnSecondCall(): void
    {
        $entityTypeMock = $this->createMock(EntityType::class);
        $entityTypeMock->method('getEntityTypeId')->willReturn('4');

        $this->eavConfigMock
            ->expects($this->once())
            ->method('getEntityType')
            ->with(ProductAttributeInterface::ENTITY_TYPE_CODE)
            ->willReturn($entityTypeMock);

        $firstResult = $this->resolver->getProductEntityTypeId();
        $secondResult = $this->resolver->getProductEntityTypeId();

        $this->assertSame(4, $firstResult);
        $this->assertSame(4, $secondResult);
    }

    public function testCategoryEntityTypeIdIsCachedOnSecondCall(): void
    {
        $entityTypeMock = $this->createMock(EntityType::class);
        $entityTypeMock->method('getEntityTypeId')->willReturn('3');

        $this->eavConfigMock
            ->expects($this->once())
            ->method('getEntityType')
            ->with(CategoryAttributeInterface::ENTITY_TYPE_CODE)
            ->willReturn($entityTypeMock);

        $firstResult = $this->resolver->getCategoryEntityTypeId();
        $secondResult = $this->resolver->getCategoryEntityTypeId();

        $this->assertSame(3, $firstResult);
        $this->assertSame(3, $secondResult);
    }

    public function testProductAndCategoryCachesAreIndependent(): void
    {
        $productTypeMock = $this->createMock(EntityType::class);
        $productTypeMock->method('getEntityTypeId')->willReturn('4');

        $categoryTypeMock = $this->createMock(EntityType::class);
        $categoryTypeMock->method('getEntityTypeId')->willReturn('3');

        $this->eavConfigMock
            ->expects($this->exactly(2))
            ->method('getEntityType')
            ->willReturnCallback(function (string $code) use ($productTypeMock, $categoryTypeMock) {
                return match ($code) {
                    ProductAttributeInterface::ENTITY_TYPE_CODE => $productTypeMock,
                    CategoryAttributeInterface::ENTITY_TYPE_CODE => $categoryTypeMock,
                };
            });

        $this->assertSame(4, $this->resolver->getProductEntityTypeId());
        $this->assertSame(3, $this->resolver->getCategoryEntityTypeId());
        // Second calls should use cache -- no additional getEntityType calls
        $this->assertSame(4, $this->resolver->getProductEntityTypeId());
        $this->assertSame(3, $this->resolver->getCategoryEntityTypeId());
    }
}
