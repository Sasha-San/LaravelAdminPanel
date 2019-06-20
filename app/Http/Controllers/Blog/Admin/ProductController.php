<?php

    namespace App\Http\Controllers\Blog\Admin;


    use App\Http\Requests\AdminProductsCreateRequest;
    use App\Models\Admin\Category;
    use App\Models\Admin\Product;
    use App\Repositories\Admin\ProductRepository;
    use Config;
    use Illuminate\Http\Request;
    use MetaTag;
    use Illuminate\Support\Facades\File;
    use Illuminate\Support\Facades\Validator;
    use App\SBlog\Core\BlogApp;
    use Session;


    class ProductController extends AdminBaseController
    {


        private $productRepository;


        public function __construct()
        {
            parent::__construct();
            $this->productRepository = app(ProductRepository::class);
        }

        /**
         * INDEX PAGE
         *
         * @return \Illuminate\Http\Response
         */
        public function index()
        {
            $perpage = 10;
            $getAllProducts = $this->productRepository->getAllProducts($perpage);
            $count = $this->productRepository->getCountProducts();


            MetaTag::setTags(['title' => 'Список товаров']);
            return view('blog.admin.product.index', compact('getAllProducts', 'count'));
        }

        /**
         * Show the form for creating a new resource.
         *
         * @return \Illuminate\Http\Response
         */
        public function create()
        {
            $_SESSION['ckfinder_auth'] = true;

            $item = new Category();

            MetaTag::setTags(['title' => 'Создание нового товара']);

            return view('blog.admin.product.create', [
                'categories' => Category::with('children')->where('parent_id', '0')
                    ->get(),
                'delimiter' => '-',
                'item' => $item,
            ]);
        }

        /**
         * Store a newly created resource in storage.
         *
         * @param AdminProductsCreateRequest $request
         * @return \Illuminate\Http\RedirectResponse
         */
        public function store(AdminProductsCreateRequest $request)
        {

            $data = $request->input();

            $product = (new Product())->create($data);

            $product->status = $request->status ? '1' : '0';
            $product->hit = $request->hit ? '1' : '0';
            $product->category_id = $request->parent_id ?? '0';
            $product->img = $this->productRepository->getImg();


            $save = $product->save();
            $id = $product->id;

            $this->productRepository->editFilter($id, $data);

            $this->productRepository->editRelatedProduct($id, $data);

            $this->productRepository->saveGallery($id);

            if ($save) {
                return redirect()
                    ->route('blog.admin.products.create', [$product->id])
                    ->with(['success' => 'Успешно сохранено']);
            } else {
                return back()
                    ->withErrors(['msg' => 'Ошибка сохранения'])
                    ->withInput();
            }

        }


        /** Upload Single Image */
        public function ajaxImage(Request $request)
        {
            if ($request->isMethod('get')) {
                return view('blog.admin.product.single_image');
            } else {
                $validator = Validator::make($request->all(),
                    [
                        'file' => 'image|max:1000',
                    ],
                    [
                        'file.image' => 'Файл должен быть картинкой (jpeg, png, bmp, gif, or svg)',
                        'file.max' => 'Ошибка! Максимальный вес файла - 1 Мб!',
                    ]);
                if ($validator->fails()) {
                    return array(
                        'fail' => true,
                        'errors' => $validator->errors()
                    );
                }
                $extension = $request->file('file')->getClientOriginalExtension();
                $dir = 'uploads/single/';
                $filename = uniqid() . '_' . time() . '.' . $extension;
                $request->file('file')->move($dir, $filename);

                $wmax = BlogApp::get_instance()->getProperty('img_width');
                $hmax = BlogApp::get_instance()->getProperty('img_height');

                $this->productRepository->uploadImg($filename,$wmax,$hmax);

                return $filename;
            }
        }

        /** Upload Gallery Images
         *
         * @param Request $request
         * @return array|\Illuminate\Contracts\View\Factory|\Illuminate\View\View|string
         */
        public function ajaxImages(Request $request)
        {
            if ($request->isMethod('get')) {
                return view('blog.admin.product.include.gallery_image');
            } else {
                $validator = Validator::make($request->all(),
                    [
                        'file' => 'image|max:3000',
                    ],
                    [
                        'file.image' => 'Файл должен быть картинкой (jpeg, png, bmp, gif, or svg)',
                        'file.max' => 'Ошибка! Максимальный вес файла - 3 Мб!',
                    ]);
                if ($validator->fails()) {
                    return array(
                        'fail' => true,
                        'errors' => $validator->errors()
                    );
                }
                $extension = $request->file('file')->getClientOriginalExtension();
                $dir = 'uploads/gallery/';
                $filename = uniqid() . '_' . time() . '.' . $extension;
                $request->file('file')->move($dir, $filename);

                $wmax = BlogApp::get_instance()->getProperty('gallery_width');
                $hmax = BlogApp::get_instance()->getProperty('gallery_height');

                $this->productRepository->uploadImgs($filename,$wmax,$hmax);

                return $filename;
            }
        }

        /** Delete Image */
        public function deleteImage($filename)
        {
            File::delete('uploads/single/' . $filename);
        }

        /** Delete Image from Gallery */
        public function deleteGalleryImage($filename)
        {
            File::delete('uploads/gallery/' . $filename);
        }



        /** Related Products */
        public function related(Request $request)
        {
            $q = isset($request->q) ? htmlspecialchars(trim($request->q)) : '';
            $data['items'] = [];

            $products = \DB::table('products')
                ->select('id', 'title')
                ->where('title', 'LIKE', ["%{$q}%"])
                ->limit(8)
                ->get();

            if ($products) {
                $i = 0;
                foreach ($products as $id => $title) {
                    $data['items'][$i]['id'] = $title->id;
                    $data['items'][$i]['text'] = $title->title;
                    $i++;
                }
            }

            echo json_encode($data);
            die;
        }


        /**
         * Display the specified resource.
         *
         * @param  int $id
         * @return \Illuminate\Http\Response
         */
        public function show($id)
        {
            //
        }

        /**
         * Show the form for editing the specified resource.
         *
         * @param  int $id
         * @return \Illuminate\Http\Response
         */
        public function edit($id)
        {
            //
        }

        /**
         * Update the specified resource in storage.
         *
         * @param  \Illuminate\Http\Request $request
         * @param  int $id
         * @return \Illuminate\Http\Response
         */
        public function update(Request $request, $id)
        {
            //
        }

        /**
         * Remove the specified resource from storage.
         *
         * @param  int $id
         * @return \Illuminate\Http\Response
         */
        public function destroy($id)
        {
            //
        }
    }
