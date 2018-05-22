<?php

declare(strict_types=1);

namespace Pim\Bundle\EnrichBundle\tests\integration\CategoryTree\Query\ElasticsearchAndSql;

use Akeneo\Test\Integration\Configuration;
use Akeneo\Test\Integration\TestCase;
use PHPUnit\Framework\Assert;
use Pim\Component\Enrich\CategoryTree\ReadModel\ChildCategory;
use Pim\Component\Enrich\CategoryTree\ReadModel\RootCategory;

class ListRootCategoriesWithCountNotIncludingSubCategoriesIntegration extends TestCase
{
    /**
     * @inheritDoc
     */
    protected function setUp()
    {
        parent::setUp();

        $this->givenTheCategoryTrees([
            'tree_1' => [
                'tree_1_child_1_level_1' => [
                    'tree_1_child_1_level_2' => [
                        'tree_1_child_1_level_3' => []
                    ],
                    'tree_1_child_2_level_2' => [],
                ],
                'tree_1_child_2_level_1' => [],
                'tree_1_child_3_level_1' => [],
            ],
            'tree_2' => [
                'tree_2_child_1_level_1' => [
                    'tree_2_child_1_level_2' => [],
                    'tree_2_child_2_level_2' => [],
                    'tree_2_child_2_level_3' => [],
                ]
            ]
        ]);
        $this->givenTheProductsWithCategories([
            'product_1' => ['tree_1_child_1_level_1', 'tree_1_child_1_level_2'],
            'product_2' => ['tree_1_child_1_level_3', 'tree_2'],
            'product_3' => ['tree_2_child_2_level_3', 'tree_1_child_2_level_1']
        ]);
    }

    /**
     * @test
     */
    public function listRootCategories()
    {
        $query = $this->get('pim_enrich.category_tree.query.list_root_categories_with_count_not_including_sub_categories');
        $locale = $this->get('pim_catalog.repository.locale')->findOneByIdentifier('en_US');
        $user = $this->get('pim_user.repository.user')->findOneByIdentifier('admin');
        $rootCategoryIdToExpand = $this->get('pim_catalog.repository.category')->findOneByIdentifier('tree_1');

        $result = $query->list($locale, $user, $rootCategoryIdToExpand->getId());

        $expectedCategories = [
            new RootCategory(1, 'master', 'Master catalog', 0, false),
            new RootCategory(1, 'tree_1', 'Tree_1', 0, true),
            new RootCategory(3, 'tree_2', 'Tree_2', 1, false),
        ];

        $this->assertSameListOfRootCategories($expectedCategories, $result);
    }

    /**
     * @param RootCategory[] $expectedCategories
     * @param RootCategory[] $categories
     */
    private function assertSameListOfRootCategories(array $expectedCategories, array $categories): void
    {
        $i = 0;
        foreach ($expectedCategories as $expectedCategory) {
            $this->assertSameRootCategory($expectedCategory, $categories[$i]);
            $i++;
        }
    }

    /**
     * @param RootCategory $expectedCategory
     * @param RootCategory $category
     */
    private function assertSameRootCategory(RootCategory $expectedCategory, RootCategory $category): void
    {
        Assert::assertEquals($expectedCategory->code(), $category->code());
        Assert::assertEquals($expectedCategory->selected(), $category->selected());
        Assert::assertEquals($expectedCategory->label(), $category->label());
        Assert::assertEquals($expectedCategory->numberProductsInCategory(), $category->numberProductsInCategory());
    }

    /**
     * @param array       $categories
     * @param null|string $parentCode
     */
    private function givenTheCategoryTrees(array $categories, ?string $parentCode = null): void
    {
        foreach ($categories as $categoryCode => $children) {
            $category = $this->get('pim_catalog.factory.category')->create();
            $this->get('pim_catalog.updater.category')->update($category, [
                'code' => $categoryCode,
                'parent' => $parentCode ?? null,
                'labels' => ['en_US' => ucfirst($categoryCode)]
            ]);
            Assert::assertEquals(0, $this->get('validator')->validate($category)->count());
            $this->get('pim_catalog.saver.category')->save($category);

            $this->givenTheCategoryTrees($children, $categoryCode);
        }
    }

    /**
     * @param array $products
     */
    private function givenTheProductsWithCategories(array $products): void
    {
        foreach ($products as $identifier => $categories) {
            $product = $this->get('pim_catalog.builder.product')->createProduct($identifier);
            $this->get('pim_catalog.updater.product')->update($product, [
                'categories' => $categories
            ]);
            $constraintList = $this->get('pim_catalog.validator.product')->validate($product);
            Assert::assertEquals(0, $constraintList->count());
            $this->get('pim_catalog.saver.product')->save($product);
        }

        $this->get('akeneo_elasticsearch.client.product')->refreshIndex();
    }

    /**
     * @inheritDoc
     */
    protected function getConfiguration(): Configuration
    {
        return $this->catalog->useMinimalCatalog();
    }
}

