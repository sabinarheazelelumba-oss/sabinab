<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Model: ProductModel
 *
 * All SQL for the `products` table lives here, so controllers never
 * touch the database directly.
 */
class ProductModel extends Model {

    protected $table       = 'products';
    protected $primary_key = 'id';
    protected $fillable    = ['product_name', 'description', 'price', 'quantity'];
    protected $guarded     = ['id', 'created_at'];

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * List products (newest first). Optional search on name/description.
     */
    public function get_products($search = '')
    {
        if ($search !== '') {
            $like = '%' . $search . '%';
            $stmt = $this->db->raw(
                "SELECT id, product_name, description, price, quantity, created_at
                   FROM products
                  WHERE product_name LIKE ? OR description LIKE ?
                  ORDER BY id DESC",
                [$like, $like]
            );
        } else {
            $stmt = $this->db->raw(
                "SELECT id, product_name, description, price, quantity, created_at
                   FROM products
                  ORDER BY id DESC"
            );
        }

        return array_map([$this, 'format'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Get one product or NULL.
     */
    public function find_product($id)
    {
        $stmt = $this->db->raw(
            "SELECT id, product_name, description, price, quantity, created_at
               FROM products
              WHERE id = ? LIMIT 1",
            [(int) $id]
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->format($row) : null;
    }

    /**
     * Insert a product and return the new row.
     */
    public function create_product(array $data)
    {
        $data = $this->fillable_attributes($data);
        $this->db->table($this->table)->insert($data);

        return $this->find_product($this->db->last_id());
    }

    /**
     * Update a product and return the fresh row.
     */
    public function update_product($id, array $data)
    {
        $data = $this->fillable_attributes($data);

        if (!empty($data)) {
            $this->db->table($this->table)
                     ->where($this->primary_key, (int) $id)
                     ->update($data);
        }

        return $this->find_product($id);
    }

    /**
     * Delete a product.
     */
    public function delete_product($id)
    {
        return $this->db->table($this->table)
                        ->where($this->primary_key, (int) $id)
                        ->delete();
    }

    /**
     * Cast DB strings to proper types, and undo the HTML-escaping that
     * the API library applies to incoming text (React escapes on output).
     */
    private function format(array $row)
    {
        return [
            'id'           => (int) $row['id'],
            'product_name' => html_entity_decode((string) $row['product_name'], ENT_QUOTES, 'UTF-8'),
            'description'  => $row['description'] === null
                                ? ''
                                : html_entity_decode((string) $row['description'], ENT_QUOTES, 'UTF-8'),
            'price'        => (float) $row['price'],
            'quantity'     => (int) $row['quantity'],
            'created_at'   => $row['created_at'],
        ];
    }
}
