# Product APIs Documentation

## Table of Contents
1. [Product Reset Processing API](#product-reset-processing-api)
2. [Add Product Images API](#add-product-images-api)

---

# Product Reset Processing API

## Endpoint
```
POST /api/products/reset-processing
```

## Description
This API endpoint allows you to reset the processing status of a product and optionally delete all mockup images associated with that product.

## Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `product_id` | integer | Yes | The ID of the product to reset |
| `delete_mockup` | boolean | No | If `true`, deletes all product images where `is_mockup=1` |

## Request Example

### Basic Request (Only reset is_processed)
```json
{
    "product_id": 123
}
```

### Request with Mockup Deletion
```json
{
    "product_id": 123,
    "delete_mockup": true
}
```

## Response Examples

### Success Response (without mockup deletion)
```json
{
    "status": true,
    "message": "Product processing reset successfully.",
    "data": {
        "product_id": 123,
        "is_processed": 0,
        "mockup_images_deleted": 0
    }
}
```

### Success Response (with mockup deletion)
```json
{
    "status": true,
    "message": "Product processing reset successfully.",
    "data": {
        "product_id": 123,
        "is_processed": 0,
        "mockup_images_deleted": 5
    }
}
```

### Error Response (Product not found)
```json
{
    "status": false,
    "message": "Product not found."
}
```

### Error Response (Validation Error)
```json
{
    "status": false,
    "errors": {
        "product_id": ["The product id field is required."]
    }
}
```

## What This API Does

1. **Sets `is_processed = 0`**: Marks the product as unprocessed in the `products` table
2. **Deletes Mockup Images** (if `delete_mockup=true`):
   - Finds all records in `product_images` where `product_id` matches and `is_mockup=1`
   - Deletes the physical image files from storage
   - Removes the database records

## Usage Example (cURL)

```bash
curl -X POST http://your-domain.com/api/products/reset-processing \
  -H "Content-Type: application/json" \
  -d '{
    "product_id": 123,
    "delete_mockup": true
  }'
```

## Usage Example (JavaScript/Fetch)

```javascript
fetch('http://your-domain.com/api/products/reset-processing', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    product_id: 123,
    delete_mockup: true
  })
})
.then(response => response.json())
.then(data => console.log(data))
.catch(error => console.error('Error:', error));
```

## Database Changes Made

### Product Model
Added to fillable array:
- `is_processed`
- `description`
- `suitable_for`

### ProductImage Model
Added to fillable array:
- `just_product`

---

# Add Product Images API

## Endpoint
```
POST /api/products/images/add
```

## Description
This API endpoint allows you to add new images to an existing product with optional tags like `is_mockup` and `just_product`.

## Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `product_id` | integer | Yes | The ID of the product to add images to |
| `images` | file/array | Yes | Single image or array of images to upload |
| `name` | string | No | Update product name |
| `description` | string | No | Update product description |
| `suitable_for` | string | No | Update product suitable_for field |
| `is_mockup` | integer | No | Set to `1` to mark images as mockup (default: 0) |
| `mockup_description` | string | No | Description for mockup images (only when is_mockup=1) |
| `just_product` | integer | No | Set to `1` to mark images as just_product (default: 0) |

## Request Example

### Upload Regular Product Images
```http
POST /api/products/images/add
Content-Type: multipart/form-data

product_id: 123
images: [file1.jpg, file2.jpg]
name: "Beautiful Artwork"
description: "A stunning piece"
```

### Upload Images with is_mockup Tag
```http
POST /api/products/images/add
Content-Type: multipart/form-data

product_id: 123
images: [mockup1.jpg, mockup2.jpg]
is_mockup: 1
mockup_description: "Wall mockup in living room"
```

### Upload Images with just_product Tag
```http
POST /api/products/images/add
Content-Type: multipart/form-data

product_id: 123
images: [product1.jpg, product2.jpg]
just_product: 1
```

### Upload Images with Both Tags
```http
POST /api/products/images/add
Content-Type: multipart/form-data

product_id: 123
images: [image1.jpg, image2.jpg]
is_mockup: 1
just_product: 1
mockup_description: "Product mockup"
```

## Response Examples

### Success Response
```json
{
    "status": true,
    "message": "3 image(s) added successfully.",
    "data": [
        {
            "id": 456,
            "product_id": 123,
            "image": "public/product_images/product_123_1234567890_abc123.jpg",
            "is_mockup": 1,
            "just_product": 1,
            "mockup_description": "Product mockup",
            "created_at": "2024-12-04T10:30:00.000000Z",
            "updated_at": "2024-12-04T10:30:00.000000Z"
        }
    ]
}
```

### Error Response (No images uploaded)
```json
{
    "status": false,
    "message": "No valid images were uploaded."
}
```

### Error Response (Product not found)
```json
{
    "status": false,
    "errors": {
        "product_id": ["The selected product id is invalid."]
    }
}
```

## What This API Does

1. **Uploads Images**: Accepts single or multiple image files
2. **Tags Images**: Can mark images with:
   - `is_mockup=1`: For mockup/staged product images
   - `just_product=1`: For standalone product images
   - Both tags can be used simultaneously
3. **Updates Product Info**: Optionally updates product name, description, and suitable_for fields
4. **Stores Files**: Saves images in `storage/app/public/product_images/` directory
5. **Returns Data**: Provides information about all uploaded images

## Usage Example (JavaScript/FormData)

```javascript
const formData = new FormData();
formData.append('product_id', 123);
formData.append('images[]', fileInput.files[0]);
formData.append('images[]', fileInput.files[1]);
formData.append('just_product', 1);
formData.append('name', 'Updated Product Name');
formData.append('description', 'Updated description');

fetch('http://your-domain.com/api/products/images/add', {
    method: 'POST',
    body: formData
})
.then(response => response.json())
.then(data => console.log(data))
.catch(error => console.error('Error:', error));
```

## Usage Example (cURL)

```bash
curl -X POST http://your-domain.com/api/products/images/add \
  -F "product_id=123" \
  -F "images[]=@/path/to/image1.jpg" \
  -F "images[]=@/path/to/image2.jpg" \
  -F "just_product=1" \
  -F "is_mockup=0"
```

## Image Tagging Use Cases

| Use Case | is_mockup | just_product | Description |
|----------|-----------|--------------|-------------|
| Regular product photo | 0 | 0 | Standard product image |
| Product on wall mockup | 1 | 0 | Staged/mockup image |
| Isolated product image | 0 | 1 | Just the product, no background |
| Mockup of isolated product | 1 | 1 | Both mockup and standalone |

## Notes

- The API supports both array format (`images[]`) and single file upload
- Files are automatically renamed with unique identifiers to prevent conflicts
- Previous images are not deleted; new images are added to existing ones
- Use the Product Reset API to remove mockup images if needed

