<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * ProductController  -  Product CRUD REST API
 *
 * EVERY route in this controller requires a valid Bearer access token.
 * The check lives in the constructor, so no action can forget it.
 *
 *   GET    /api/products            list products   (?search=keyword)
 *   GET    /api/products/{id}       show one product
 *   POST   /api/products            create a product
 *   PUT    /api/products/{id}       replace a product (all fields required)
 *   PATCH  /api/products/{id}       update some fields
 *   DELETE /api/products/{id}       delete a product
 */
class ProductController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        header('Content-Type: application/json; charset=utf-8');

        $this->call->library('api');
        $this->call->model('ProductModel');

        // 401 Unauthorized is sent (and the request stops) if the token is missing/invalid/expired.
        $this->api->require_jwt();
    }

    // GET /api/products
    public function index()
    {
        $query    = $this->api->get_query_params();
        $search   = trim((string) ($query['search'] ?? ''));
        $products = $this->ProductModel->get_products($search);

        $this->api->respond([
            'status' => 'success',
            'count'  => count($products),
            'data'   => $products,
        ]);
    }

    // GET /api/products/{id}
    public function show($id)
    {
        $product = $this->ProductModel->find_product($id);

        if (!$product) {
            $this->api->respond_error('Product not found.', 404);
        }

        $this->api->respond(['status' => 'success', 'data' => $product]);
    }

    // POST /api/products
    public function store()
    {
        $input = $this->api->body();
        [$data, $errors] = $this->validate($input, true);

        if ($errors) {
            $this->validation_error($errors);
        }

        $product = $this->ProductModel->create_product($data);

        $this->api->respond([
            'status'  => 'success',
            'message' => 'Product created successfully.',
            'data'    => $product,
        ], 201);
    }

    // PUT /api/products/{id}  (full)   |   PATCH /api/products/{id}  (partial)
    public function update($id)
    {
        if (!$this->ProductModel->find_product($id)) {
            $this->api->respond_error('Product not found.', 404);
        }

        $is_put = ($_SERVER['REQUEST_METHOD'] ?? '') === 'PUT';
        [$data, $errors] = $this->validate($this->api->body(), $is_put);

        if ($errors) {
            $this->validation_error($errors);
        }
        if (empty($data)) {
            $this->validation_error(['body' => 'Nothing to update.']);
        }

        $product = $this->ProductModel->update_product($id, $data);

        $this->api->respond([
            'status'  => 'success',
            'message' => 'Product updated successfully.',
            'data'    => $product,
        ]);
    }

    // DELETE /api/products/{id}
    public function destroy($id)
    {
        if (!$this->ProductModel->find_product($id)) {
            $this->api->respond_error('Product not found.', 404);
        }

        $this->ProductModel->delete_product($id);

        $this->api->respond([
            'status'  => 'success',
            'message' => 'Product deleted successfully.',
        ]);
    }

    // ------------------------------------------------------------------
    // Validation
    // ------------------------------------------------------------------
    /**
     * @param array $input
     * @param bool  $require_all  TRUE for POST/PUT, FALSE for PATCH
     * @return array [cleaned data, errors]
     */
    private function validate(array $input, $require_all)
    {
        $data   = [];
        $errors = [];

        // product_name : VARCHAR(100), required
        if (array_key_exists('product_name', $input) || $require_all) {
            $name = isset($input['product_name']) && is_scalar($input['product_name'])
                ? trim(strip_tags((string) $input['product_name']))
                : '';

            if ($name === '') {
                $errors['product_name'] = 'Product name is required.';
            } elseif (mb_strlen($name) > 100) {
                $errors['product_name'] = 'Product name must not exceed 100 characters.';
            } else {
                $data['product_name'] = $name;
            }
        }

        // description : TEXT, optional
        if (array_key_exists('description', $input)) {
            $desc = is_scalar($input['description']) ? trim(strip_tags((string) $input['description'])) : '';

            if (mb_strlen($desc) > 5000) {
                $errors['description'] = 'Description must not exceed 5000 characters.';
            } else {
                $data['description'] = $desc;
            }
        } elseif ($require_all) {
            $data['description'] = '';
        }

        // price : DECIMAL(10,2), required, >= 0
        if (array_key_exists('price', $input) || $require_all) {
            $price = $input['price'] ?? null;

            if (!is_scalar($price) || $price === '' || !is_numeric($price)) {
                $errors['price'] = 'Price must be a number.';
            } elseif ((float) $price < 0 || (float) $price > 99999999.99) {
                $errors['price'] = 'Price must be between 0 and 99,999,999.99.';
            } else {
                $data['price'] = round((float) $price, 2);
            }
        }

        // quantity : INT, required, >= 0
        if (array_key_exists('quantity', $input) || $require_all) {
            $qty = $input['quantity'] ?? null;

            if (!is_scalar($qty) || $qty === '' || filter_var($qty, FILTER_VALIDATE_INT) === false) {
                $errors['quantity'] = 'Quantity must be a whole number.';
            } elseif ((int) $qty < 0 || (int) $qty > 2147483647) {
                $errors['quantity'] = 'Quantity must be 0 or more.';
            } else {
                $data['quantity'] = (int) $qty;
            }
        }

        return [$data, $errors];
    }

    private function validation_error(array $errors)
    {
        $this->api->respond([
            'error'  => 'Validation failed.',
            'status' => 422,
            'errors' => $errors,
        ], 422);
    }
}
