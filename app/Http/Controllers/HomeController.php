<?php

namespace App\Http\Controllers;

use App\Product;
use App\ProductCategory;
use Illuminate\Http\Request;
use DB;

class HomeController extends Controller
{
    public function index()
    {
        $products = Product::with('categories.parentCategory.parentCategory')
            ->inRandomOrder()
            ->take(9)
            ->get();

        return view('index', compact('products'));
    }
    public function test()
    {
        $frontCategories = ProductCategory::whereNull('category_id')->get()->toArray();

        foreach ($frontCategories as $p_key => $parentCategory) {
            $frontCategories[$p_key]['child_categories'] = $this->getChildCategorie($parentCategory['id']);
            foreach ($frontCategories[$p_key]['child_categories'] as $p1_key => $parentCategory1) {
                $frontCategories[$p_key]['child_categories'][$p1_key]['child_categories'] = $this->getChildCategorie($parentCategory1['id']);
            }
        }
        foreach ($frontCategories as $parent_key => $value) {
            $list_ids = [];
            foreach ($frontCategories[$parent_key]['child_categories'] as $key => $value1) {
                $list_ids = array_merge($list_ids,array_column($this->getChildren($value1['id']),'id'));
                $frontCategories[$parent_key]['child_categories'][$key]['product_count'] = (int) $this->countProduct(array_column($this->getChildren($value1['id']),'id'));

                foreach ($frontCategories[$parent_key]['child_categories'][$key]['child_categories']  as $key_1 => $value_1) {
                    $frontCategories[$parent_key]['child_categories'][$key]['child_categories'][$key_1]['product_count'] = (int) $this->countProduct([$value_1['id']]);
                    
                }
            }
            $frontCategories[$parent_key]['product_count'] = (int) $this->countProduct($list_ids);
        }

        return $frontCategories;
    }
    public function getChildren($id)
    {
        $childCategories = ProductCategory::where('category_id',$id)->get()->toArray();
        return $childCategories;
    }
    public function countProduct($list_ids)
    {
        $result = DB::table('product_product_category')
        ->select(DB::raw('count(*) as _count'))
        ->whereIn('product_product_category.product_category_id', $list_ids)
        ->get()->toArray();
        return $result[0]->_count;
    }
    public function getChildCategorie($id)
    {
        $childCategories = ProductCategory::where('category_id',$id)->get()->toArray();

        return $childCategories;
    }

    public function category(ProductCategory $category, ProductCategory $childCategory = null, $childCategory2 = null)
    {
        $products = null;
        $ids = collect();
        $selectedCategories = [];

        if ($childCategory2) {
            $subCategory = $childCategory->childCategories()->where('slug', $childCategory2)->firstOrFail();
            $ids = collect($subCategory->id);
            $selectedCategories = [$category->id, $childCategory->id, $subCategory->id];
        } elseif ($childCategory) {
            $ids = $childCategory->childCategories->pluck('id');
            $selectedCategories = [$category->id, $childCategory->id];
        } elseif ($category) {
            $category->load('childCategories.childCategories');
            $ids = collect();
            $selectedCategories[] = $category->id;
            
            foreach ($category->childCategories as $subCategory) {
                $ids = $ids->merge($subCategory->childCategories->pluck('id'));
            }
        }
        $products = Product::whereHas('categories', function ($query) use ($ids) {
            $query->whereIn('id', $ids);
        })
        ->with('categories.parentCategory.parentCategory')
        ->paginate(9);
        return view('index', compact('products', 'selectedCategories'));
    }

    public function product($category, $childCategory, $childCategory2, $productSlug, Product $product)
    {
        $product->load('categories.parentCategory.parentCategory');
        $childCategory2 = $product->categories->where('slug', $childCategory2)->first();
        $selectedCategories = [];

        if ($childCategory2 &&
            $childCategory2->parentCategory &&
            $childCategory2->parentCategory->parentCategory
        ) {
            $selectedCategories = [
                $childCategory2->parentCategory->parentCategory->id ?? null,
                $childCategory2->parentCategory->id ?? null,
                $childCategory2->id
            ];
        }

        return view('product', compact('product', 'selectedCategories'));
    }
}
