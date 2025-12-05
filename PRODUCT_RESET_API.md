# Product APIs Documentation

## Table of Contents
1. [Product Reset Processing API](#product-reset-processing-api)
2. [Add Product Images API](#add-product-images-api)
3. [Get Products with Images API](#get-products-with-images-api)

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

---

# Get Products with Images API

## Endpoint
```
GET /api/products-with-images
```

## Description
Retrieves products with their associated images, categories, and relationships. Supports filtering, sorting, and pagination.

## Query Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `product_id` | integer | Yes* | - | Get specific product by ID |
| `business_id` | integer | Yes* | - | Filter by business ID |
| `location_id` | integer | Yes* | - | Filter by location ID |
| `limit` | integer | No | 20 | Number of items per page (max 100) |
| `page` | integer | No | 1 | Page number for pagination |
| `sort_by` | string | No | id | Field to sort by |
| `sort_order` | string | No | desc | Sort direction: `asc` or `desc` |

\* At least one of `product_id`, `business_id`, or `location_id` is required

## Allowed Sort Fields

You can sort by any of these fields:
- `id` - Product ID
- `name` - Product name
- `price` - Product price
- `created_at` - Creation date
- `updated_at` - Last update date
- `product_serial_number` - Serial number
- `artist_name` - Artist name
- `quantity` - Stock quantity
- `height` - Product height
- `width` - Product width
- `item_code` - Item code
- `hsn_code` - HSN code

## Request Examples

### Get Specific Product by ID
```
GET /api/products-with-images?product_id=123
```

### Get Specific Product with Additional Filters
```
GET /api/products-with-images?product_id=123&business_id=1
```

### Basic Request
```
GET /api/products-with-images?business_id=1&location_id=2
```

### With Sorting and Pagination
```
GET /api/products-with-images?business_id=1&location_id=2&limit=10&page=1&sort_by=id&sort_order=desc
```

### Sort by Name (Ascending)
```
GET /api/products-with-images?business_id=1&sort_by=name&sort_order=asc&limit=25
```

### Sort by Price (Descending)
```
GET /api/products-with-images?location_id=5&sort_by=price&sort_order=desc&limit=50&page=2
```

### Sort by Creation Date (Latest First)
```
GET /api/products-with-images?business_id=1&sort_by=created_at&sort_order=desc&limit=15
```

## Response Example

### Success Response
```json
{
    "status": true,
    "message": "Products retrieved successfully.",
    "data": [
        {
            "id": 123,
            "business_id": 1,
            "location_id": 2,
            "name": "Beautiful Landscape Painting",
            "price": 15000.00,
            "product_serial_number": "ART-001",
            "height": 60,
            "width": 80,
            "is_framed": 1,
            "orientation": "landscape",
            "is_include_gst": 1,
            "artist_name": "John Doe",
            "quantity": 5,
            "category_id": 10,
            "art_category_id": 5,
            "item_code": "ITEM-001",
            "hsn_code": "9701",
            "is_customizable": 0,
            "description": "A stunning landscape painting",
            "suitable_for": "Living room, Office",
            "is_processed": 1,
            "is_temp": 0,
            "created_at": "2024-12-01T10:00:00.000000Z",
            "updated_at": "2024-12-04T15:30:00.000000Z",
            "category": {
                "id": 10,
                "name": "Paintings",
                "description": "Art paintings"
            },
            "art_category": {
                "id": 5,
                "name": "Landscapes",
                "category_id": 10
            },
            "images": [
                {
                    "id": 456,
                    "product_id": 123,
                    "image": "public/product_images/product_123_1234567890_abc.jpg",
                    "is_mockup": 0,
                    "just_product": 1,
                    "mockup_description": null,
                    "created_at": "2024-12-01T10:00:00.000000Z",
                    "updated_at": "2024-12-01T10:00:00.000000Z"
                },
                {
                    "id": 457,
                    "product_id": 123,
                    "image": "public/product_images/product_123_1234567891_def.jpg",
                    "is_mockup": 1,
                    "just_product": 0,
                    "mockup_description": "Wall mockup",
                    "created_at": "2024-12-02T11:00:00.000000Z",
                    "updated_at": "2024-12-02T11:00:00.000000Z"
                }
            ]
        }
    ],
    "pagination": {
        "current_page": 1,
        "per_page": 20,
        "total": 150,
        "last_page": 8,
        "from": 1,
        "to": 20
    },
    "sort": {
        "sort_by": "id",
        "sort_order": "desc"
    }
}
```

### Error Response (Missing Required Parameters)
```json
{
    "status": false,
    "message": "Either product_id, business_id, or location_id is required."
}
```

### Error Response (Server Error)
```json
{
    "status": false,
    "message": "Failed to retrieve products.",
    "error": "Database connection error"
}
```

## What This API Returns

1. **Product Data**: Complete product information including:
   - Basic details (name, price, dimensions)
   - Serial numbers and codes
   - Artist information
   - Stock quantity
   - Customization options

2. **Related Data**:
   - `category`: Main product category
   - `art_category`: Art-specific sub-category
   - `images`: All associated product images with tags

3. **Pagination Info**:
   - Current page number
   - Items per page
   - Total items count
   - Last page number
   - Range (from-to)

4. **Sort Info**:
   - Active sort field
   - Sort direction

## Usage Example (JavaScript)

```javascript
async function getProducts(businessId, locationId, page = 1, limit = 20) {
    const params = new URLSearchParams({
        business_id: businessId,
        location_id: locationId,
        limit: limit,
        page: page,
        sort_by: 'id',
        sort_order: 'desc'
    });
    
    const response = await fetch(`/api/products-with-images?${params}`);
    const data = await response.json();
    
    if (data.status) {
        console.log('Products:', data.data);
        console.log('Total:', data.pagination.total);
        console.log('Current Page:', data.pagination.current_page);
    }
    
    return data;
}

// Usage
getProducts(1, 2, 1, 10);
```

## Usage Example (cURL)

```bash
curl -X GET "http://your-domain.com/api/products-with-images?business_id=1&location_id=2&limit=10&page=1&sort_by=id&sort_order=desc"
```

## Usage Example (Java/Android - as shown in your code)

### Get All Products with Filters
```java
String base_url = "http://your-domain.com";
int businessId = 1;
int locationId = 2;
int limit = 20;
int page = 1;

String url = base_url + "/api/products-with-images" +
    "?business_id=" + businessId + 
    "&location_id=" + locationId + 
    "&limit=" + limit + 
    "&page=" + page + 
    "&sort_by=id" +
    "&sort_order=desc";

// Make HTTP GET request to url
```

### Get Specific Product by ID
```java
String base_url = "http://your-domain.com";
int productId = 123;

String url = base_url + "/api/products-with-images?product_id=" + productId;

// Make HTTP GET request to url
```

## Performance Notes

- **Maximum limit**: 100 items per page (enforced for performance)
- **Default limit**: 20 items per page
- **Filters**: Using both `business_id` and `location_id` provides fastest results
- **Sorting**: Sorting by indexed fields (`id`, `created_at`) is faster
- **Temp products**: Products with `is_temp=1` are automatically excluded

## Common Use Cases

### Get Single Product Details
```
?product_id=123
```

### Latest Products First
```
?business_id=1&sort_by=created_at&sort_order=desc&limit=10
```

### Cheapest Products
```
?business_id=1&sort_by=price&sort_order=asc&limit=20
```

### Products by Artist (A-Z)
```
?business_id=1&sort_by=artist_name&sort_order=asc&limit=50
```

### Low Stock Products
```
?business_id=1&sort_by=quantity&sort_order=asc&limit=25
```

### Search Specific Product in Location
```
?product_id=123&location_id=5
```

## Filter Combinations

You can combine filters in various ways:

| Use Case | Parameters | Description |
|----------|------------|-------------|
| Single product | `product_id=123` | Get one specific product |
| All business products | `business_id=1` | All products for a business |
| All location products | `location_id=5` | All products for a location |
| Business + Location | `business_id=1&location_id=5` | Products in specific location of business |
| Product + Business | `product_id=123&business_id=1` | Verify product belongs to business |
| Product + Location | `product_id=123&location_id=5` | Verify product is in location |

