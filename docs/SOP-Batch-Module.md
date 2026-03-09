# SOP — Batch Module

**System:** SFX Backend (Laravel)  
**Version:** 1.0  
**Date:** 2026-03-09  
**Scope:** Batch model, BatchRepository, BatchController, and related API routes

---

## 1. Overview

The Batch module manages production batches, each linked to a **Model** (style/product model). A batch holds a batch number and a quantity breakdown stored as JSON (`qty_json`). Soft-deletion is implemented via an `active` flag — records are never physically removed from the database.

---

## 2. Architecture

```
HTTP Request
    │
    ▼
BatchController  (app/Http/Controllers/Api/BatchController.php)
    │
    ▼
BatchRepository  (app/Http/Repositories/BatchRepository.php)
    │
    ▼
Batch Model      (app/Batch.php)
    │
    ▼
batches table (MySQL)
```

---

## 3. File Reference

| File | Path | Responsibility |
|------|------|----------------|
| Batch Model | `app/Batch.php` | Eloquent model, fillable fields, casts, global scope, relationships |
| BatchRepository | `app/Http/Repositories/BatchRepository.php` | Business logic, database queries, validation |
| BatchController | `app/Http/Controllers/Api/BatchController.php` | HTTP layer, delegates all logic to repository |
| API Routes | `routes/api.php` | Route definitions under `/api/v1/Batch/` |

---

## 4. Database Table — `batches`

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT (PK) | Auto-increment primary key |
| `model_id` | INT (FK) | Foreign key to `models.id` |
| `batch_no` | VARCHAR | Unique batch number |
| `qty_json` | JSON | Quantity breakdown per size/color |
| `active` | BOOLEAN | Soft-delete flag (`true` = active) |
| `created_by` | INT | User ID who created the record |
| `updated_by` | INT | User ID who last updated the record |
| `created_at` | TIMESTAMP | Auto-managed by Laravel |
| `updated_at` | TIMESTAMP | Auto-managed by Laravel |

---

## 5. Model — `Batch` (`app/Batch.php`)

### 5.1 Fillable Fields
```
model_id, batch_no, qty_json, active, created_by, updated_by
```

### 5.2 Casts
| Field | Cast Type |
|-------|-----------|
| `model_id` | `integer` |
| `qty_json` | `array` |
| `active` | `boolean` |

### 5.3 Global Scope
A global scope named **`active`** is applied automatically to every query:
```php
$query->where('active', true);
```
This ensures **inactive (soft-deleted) batches are never returned** by any query unless explicitly bypassed with `withoutGlobalScope('active')`.

### 5.4 Boot Hooks
- **Creating:** Sets `created_by` and `updated_by` to the authenticated user's ID.
- **Updating:** Sets `updated_by` to the authenticated user's ID.

### 5.5 Relationships
| Relationship | Type | Related Model | Foreign Key |
|--------------|------|---------------|-------------|
| `model()` | `belongsTo` | `App\Model` | `model_id` |

---

## 6. API Endpoints

All routes require **Bearer Token authentication** (`auth:api` middleware) and are prefixed with `/api/v1/`.

---

### 6.1 Get All Batches

| Property | Value |
|----------|-------|
| **Method** | `GET` |
| **URL** | `/api/v1/Batch/getBatches` |
| **Auth** | Required |

**Request:** No body required.

**Response (200):**
```json
[
  {
    "id": 1,
    "model_id": 2,
    "batch_no": "B-001",
    "qty_json": { "S": 100, "M": 150, "L": 120 },
    "active": true,
    "created_by": 1,
    "updated_by": 1,
    "created_at": "2026-03-01 10:00:00",
    "updated_at": "2026-03-01 10:00:00",
    "model": {
      "id": 2,
      "name": "ModelA"
    }
  }
]
```

**Notes:**
- Returns only `active = true` records (enforced by global scope).
- Results are ordered by `id DESC`.
- Eager-loads the `model` relation.

---

### 6.2 Create or Update Batch

| Property | Value |
|----------|-------|
| **Method** | `POST` |
| **URL** | `/api/v1/Batch/createAndUpdateBatch` |
| **Auth** | Required |

**Request Body:**
```json
{
  "id": null,
  "model_id": 2,
  "batch_no": "B-001",
  "qty_json": { "S": 100, "M": 150, "L": 120 }
}
```

| Field | Required | Rules |
|-------|----------|-------|
| `id` | No | Integer, min 1. If provided → **update**, else → **create** |
| `model_id` | Yes | Integer, must exist in `models` table |
| `batch_no` | Yes | String, max 255, unique in `batches` (ignores current record on update) |
| `qty_json` | Yes | Valid JSON string or PHP array |

**Response (200):**
```json
{
  "status": "success",
  "data": {
    "id": 1,
    "batch_no": "B-001",
    "qty_json": { "S": 100, "M": 150, "L": 120 },
    "model": { "id": 2, "name": "ModelA" }
  }
}
```

**Error Responses:**

| HTTP Code | Reason |
|-----------|--------|
| 400 | Validation failed |
| 500 | Server/database error |

**Notes:**
- If `id` is `null` or omitted → creates a new batch.
- If `id` is provided → updates the existing batch.
- `created_by` / `updated_by` are set automatically from the authenticated user.

---

### 6.3 Delete Batch (Soft Delete)

| Property | Value |
|----------|-------|
| **Method** | `POST` |
| **URL** | `/api/v1/Batch/deleteBatch` |
| **Auth** | Required |

**Request Body:**
```json
{
  "id": 5
}
```

| Field | Required | Rules |
|-------|----------|-------|
| `id` | Yes | Integer, min 1 |

**Response (200):**
```json
{
  "status": "success",
  "message": "Batch deleted successfully"
}
```

**Error Responses:**

| HTTP Code | Reason |
|-----------|--------|
| 400 | Validation failed |
| 404 | Record not found |
| 500 | Server error |

**Notes:**
- This is a **soft delete** — sets `active = false`. The record is NOT physically removed.
- After deletion, the record will be invisible to all queries due to the global scope.

---

### 6.4 Search Batches

| Property | Value |
|----------|-------|
| **Method** | `POST` |
| **URL** | `/api/v1/Batch/getSearchByBatch` |
| **Auth** | Required |

**Request Body:**
```json
{
  "id": "%",
  "batch_no": "B-00",
  "model_name": "ModelA"
}
```

| Field | Description |
|-------|-------------|
| `id` | Batch ID to search. Send `"%"` to match all. |
| `batch_no` | Partial or full batch number. Send `"%"` to match all. |
| `model_name` | Partial or full model name from `models.name`. Send `"%"` to match all. |

**Response (200):**
```json
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "batch_no": "B-001",
      "model_name": "ModelA",
      "qty_json": { "S": 100, "M": 150 },
      "model_id": 2
    }
  ]
}
```

**Notes:**
- All fields support partial `LIKE` matching.
- Passing `"%"` for a field means **no filter** on that field.
- Results are ordered by `batches.id DESC`.
- Only `active = true` batches are returned.
- Joins `batches` → `models` to resolve `model_name`.

---

### 6.5 Get Batch by ID

| Property | Value |
|----------|-------|
| **Method** | `POST` |
| **URL** | `/api/v1/Batch/getBatchById` |
| **Auth** | Required |

**Request Body:**
```json
{
  "id": 5
}
```

| Field | Required | Rules |
|-------|----------|-------|
| `id` | Yes | Integer, min 1 |

**Response (200):**
```json
{
  "status": "success",
  "data": {
    "id": 5,
    "model_id": 2,
    "batch_no": "B-001",
    "qty_json": { "S": 100, "M": 150, "L": 120 },
    "active": true,
    "model": {
      "id": 2,
      "name": "ModelA"
    }
  }
}
```

**Error Responses:**

| HTTP Code | Reason |
|-----------|--------|
| 400 | Validation failed (`id` missing or invalid) |
| 404 | Batch not found or inactive |

**Notes:**
- Only returns the batch if `active = true`.
- Eager-loads the `model` relation.

---

## 7. Using `searchByParameters` for Batch

The generic search endpoint can also query batches.

| Property | Value |
|----------|-------|
| **Method** | `POST` |
| **URL** | `/api/v1/searchByParameters` |

**Payload Structure:**
- Top-level key must be the **model class name**: `Batch`
- `where` items must use `field-name`, `operator`, `value` keys
- `active` filter is **not needed** — the global scope handles it automatically

**Example Payload:**
```json
{
  "Batch": {
    "distinct": false,
    "select": ["*"],
    "where": [
      {
        "field-name": "batch_no",
        "operator": "LIKE",
        "value": "%B-00%"
      }
    ],
    "relations": ["model"],
    "orderby": "created_at:desc",
    "limit": 25
  }
}
```

---

## 8. Response Format Convention

All endpoints return a consistent JSON structure:

**Success:**
```json
{
  "status": "success",
  "data": { }
}
```

**Error:**
```json
{
  "status": "error",
  "message": "Descriptive error message or validation errors object"
}
```

---

## 9. Error Handling Summary

| Scenario | HTTP Code | `status` |
|----------|-----------|----------|
| Successful operation | 200 | `success` |
| Validation failure | 400 | `error` |
| Record not found | 404 | `error` |
| Server / database exception | 500 | `error` |

---

## 10. Soft Delete Behaviour

| Operation | Result |
|-----------|--------|
| `deleteBatch` | Sets `active = false` — record stays in DB |
| Any `GET` / `POST` query | Global scope automatically adds `WHERE active = true` |
| Bypass global scope | Use `Batch::withoutGlobalScope('active')` in code |

> **Important:** Never hard-delete a `Batch` record directly from the database. Always use the `deleteBatch` API to maintain data integrity.

---

## 11. Authentication

All Batch API endpoints are protected by the `auth:api` middleware (Laravel Passport / token-based). Every request must include:

```
Authorization: Bearer <access_token>
```

Requests without a valid token will receive `401 Unauthorized`.
