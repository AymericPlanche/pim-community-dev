<?php

declare(strict_types=1);

namespace Pim\Bundle\EnrichBundle\tests\integration\CategoryTree\Query\ElasticsearchAndSql;

use Akeneo\Test\Integration\Configuration;
use Akeneo\Test\Integration\TestCase;
use PHPUnit\Framework\Assert;
use Pim\Component\Enrich\CategoryTree\ReadModel\ChildCategory;

class ListChildrenCategoriesWithCountNotIncludingSubCategoriesIntegration extends TestCase
{
    /**
     * @inheritDoc
     */
    protected function setUp()
    {
        parent::setUp();

        $fixturesLoader = new FixturesLoader($this->testKernel->getContainer());
        $fixturesLoader->givenTheCategoryTrees([
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

        $fixturesLoader->givenTheProductsWithCategories([
            'product_1' => ['tree_1_child_1_level_1', 'tree_1_child_1_level_2'],
            'product_2' => ['tree_1_child_1_level_3', 'tree_2'],
            'product_3' => ['tree_2_child_2_level_3', 'tree_1_child_2_level_1']
        ]);
    }

    /**
     * @test
     */
    public function listChildCategories()
    {
        $query = $this->get('pim_enrich.category_tree.query.list_children_categories_with_count_not_including_sub_categories');
        $locale = $this->get('pim_catalog.repository.locale')->findOneByIdentifier('en_US');
        $user = $this->get('pim_user.repository.user')->findOneByIdentifier('admin');
        $fromCategory = $this->get('pim_catalog.repository.category')->findOneByIdentifier('tree_1');

        $result = $query->list($locale, $user, $fromCategory->getId(), null);

        $expectedCategories = [
            new ChildCategory(1, 'tree_1_child_1_level_1', 'Tree_1_child_1_level_1', false, false, 1, []),
            new ChildCategory(2, 'tree_1_child_2_level_1', 'Tree_1_child_2_level_1', false, true, 1, []),
            new ChildCategory(3, 'tree_1_child_3_level_1', 'Tree_1_child_3_level_1', false, true, 0, []),
        ];

        $this->assertSameListOfChildCategories($expectedCategories, $result);
    }

    /**
     * @test
     */
    public function listChildCategoriesFromACategoryUntilAGivenChildCategory()
    {
        $query = $this->get('pim_enrich.category_tree.query.list_children_categories_with_count_not_including_sub_categories');
        $locale = $this->get('pim_catalog.repository.locale')->findOneByIdentifier('en_US');
        $user = $this->get('pim_user.repository.user')->findOneByIdentifier('admin');
        $fromCategory = $this->get('pim_catalog.repository.category')->findOneByIdentifier('tree_1');
        $toCategory = $this->get('pim_catalog.repository.category')->findOneByIdentifier('tree_1_child_1_level_3');

        $result = $query->list($locale, $user, $fromCategory->getId(), $toCategory->getId());

        $expectedCategories = [
            new ChildCategory(1, 'tree_1_child_1_level_1', 'Tree_1_child_1_level_1', false, false, 1, [
                new ChildCategory(2, 'tree_1_child_1_level_2', 'Tree_1_child_1_level_2', false, false, 1, [
                    new ChildCategory(3, 'tree_1_child_1_level_3', 'Tree_1_child_1_level_3', true, true, 1, [])
                ]),
                new ChildCategory(4, 'tree_1_child_2_level_2', 'Tree_1_child_2_level_2', false, true, 0, [])
            ]),
            new ChildCategory(5, 'tree_1_child_2_level_1', 'Tree_1_child_2_level_1', false, true, 1, []),
            new ChildCategory(6, 'tree_1_child_3_level_1', 'Tree_1_child_3_level_1', false, true, 0, []),
        ];

        $this->assertSameListOfChildCategories($expectedCategories, $result);
    }

    /**
     * @param ChildCategory[] $expectedCategories
     * @param ChildCategory[] $categories
     */
    private function assertSameListOfChildCategories(array $expectedCategories, array $categories): void
    {
        $i = 0;
        foreach ($expectedCategories as $expectedCategory) {
            $this->assertSameChildCategory($expectedCategory, $categories[$i]);
            $i++;
        }
    }

    /**
     * @param ChildCategory $expectedCategory
     * @param ChildCategory $category
     */
    private function assertSameChildCategory(ChildCategory $expectedCategory, ChildCategory $category): void
    {
        Assert::assertEquals($expectedCategory->code(), $category->code());
        Assert::assertEquals($expectedCategory->isLeaf(), $category->isLeaf());
        Assert::assertEquals($expectedCategory->expanded(), $category->expanded());
        Assert::assertEquals($expectedCategory->label(), $category->label());
        Assert::assertEquals($expectedCategory->selectedAsFilter(), $category->selectedAsFilter());
        Assert::assertEquals($expectedCategory->numberProductsInCategory(), $category->numberProductsInCategory());
        $this->assertSameListOfChildCategories($expectedCategory->childrenCategoriesToExpand(), $category->childrenCategoriesToExpand());
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

