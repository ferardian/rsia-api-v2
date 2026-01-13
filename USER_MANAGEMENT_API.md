# User Management API Documentation

## Overview
API endpoints untuk mengelola user, role, dan assignment role ke user dalam sistem RSIA.

## Base URL
```
/api/user-management
```

## Authentication
Semua endpoints memerlukan middleware `auth:aes`.

---

## Role Management Endpoints

### Get All Roles
```http
GET /api/user-management/roles
```

**Query Parameters:**
- `active` (boolean, optional): Filter by active status
- `search` (string, optional): Search by nama_role or deskripsi

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id_role": 1,
      "nama_role": "Admin",
      "deskripsi": "Administrator role",
      "is_active": true,
      "user_roles_count": 5
    }
  ],
  "total": 1
}
```

### Create Role
```http
POST /api/user-management/roles
```

**Request Body:**
```json
{
  "nama_role": "New Role",
  "deskripsi": "Role description",
  "is_active": true
}
```

### Get Role Details
```http
GET /api/user-management/roles/{id}
```

### Update Role
```http
PUT /api/user-management/roles/{id}
```

### Delete Role
```http
DELETE /api/user-management/roles/{id}
```

### Get Role Permissions
```http
GET /api/user-management/roles/{id}/permissions
```

### Update Role Permissions
```http
POST /api/user-management/roles/{id}/permissions
```

**Request Body:**
```json
{
  "permissions": [
    {
      "id_menu": 1,
      "can_view": true,
      "can_create": false,
      "can_update": false,
      "can_delete": false,
      "can_export": false,
      "can_import": false
    }
  ]
}
```

### Get Roles with Petugas Data
```http
GET /api/user-management/roles/with-petugas
```

---

## User Role Assignment Endpoints

### Get All User Role Assignments
```http
GET /api/user-management/user-access
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "nip": "123456",
      "id_role": 1,
      "id_user": 1,
      "is_active": 1,
      "nama_pegawai": "John Doe",
      "jabatan": "Programmer",
      "nama_role": "Admin"
    }
  ],
  "total": 1
}
```

### Assign Role to User
```http
POST /api/user-management/user-access/assign
```

**Request Body:**
```json
{
  "nip": "123456",
  "access_level_id": 1,
  "user_id": 1
}
```

### Remove Role from User
```http
DELETE /api/user-management/user-access/{nip}/role/{roleId}
```

---

## Pegawai Management Endpoints

### Get All Pegawai
```http
GET /api/user-management/pegawai
```

**Query Parameters:**
- `page` (int, optional): Page number (default: 1)
- `select` (string, optional): Columns to select (default: *)

**Response:**
```json
{
  "success": true,
  "debug_message": "Using v2 PegawaiController with LEFT JOIN",
  "sample_data": {
    "id_user": "123456",
    "nip": "123456",
    "nama": "John Doe",
    "username": "123456",
    "email": null,
    "id_role": 1,
    "role_name": "Admin",
    "status": 1,
    "jbtn": "Programmer",
    "departemen": "IT",
    "photo": null,
    "created_at": null,
    "updated_at": null
  },
  "data": [...],
  "pagination": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 50,
    "total": 100,
    "from": 1,
    "to": 50
  }
}
```

### Search Pegawai
```http
GET /api/user-management/pegawai/search?q=keyword&limit=20
```

**Query Parameters:**
- `q` (string, required): Search keyword
- `limit` (int, optional): Limit results (default: 20)

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "nip": "123456",
      "nama": "John Doe",
      "jbtn": "Programmer",
      "status": "AKTIF"
    }
  ]
}
```

### Get Pegawai Details
```http
GET /api/user-management/pegawai/{nip}
```

### Get Pegawai Roles
```http
GET /api/user-management/pegawai/{nip}/roles
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "nip": "123456",
      "id_role": 1,
      "id_user": 1,
      "nama_role": "Admin",
      "deskripsi": "Administrator role"
    }
  ]
}
```

### Assign Role to Pegawai
```http
POST /api/user-management/pegawai/{nip}/role/assign
```

**Request Body:**
```json
{
  "access_level_id": 1,
  "user_id": 1
}
```

### Remove Role from Pegawai
```http
DELETE /api/user-management/pegawai/{nip}/role/{roleId}
```

### Get Pegawai Statistics
```http
GET /api/user-management/pegawai/statistics
```

**Response:**
```json
{
  "success": true,
  "data": {
    "total_pegawai": 100,
    "active_users": 100,
    "users_with_roles": 80,
    "users_without_roles": 20,
    "total_roles": 5
  }
}
```

---

## Error Responses

### Validation Error (422)
```json
{
  "success": false,
  "error": "The given data was invalid.",
  "errors": {
    "nama_role": ["The nama role field is required."]
  }
}
```

### Not Found (404)
```json
{
  "success": false,
  "error": "Resource not found"
}
```

### Server Error (500)
```json
{
  "success": false,
  "error": "Internal server error message"
}
```

---

## Database Schema Reference

### Tables Used:
- `pegawai` - Data pegawai dari database sikrsia
- `rsia_role` - Role/akses level
- `rsia_user_role` - Assignment role ke user
- `rsia_role_menu` - Permissions role ke menu

### Key Fields:
- `pegawai.nik` - NIP pegawai (unique identifier)
- `rsia_role.id_role` - Role ID
- `rsia_user_role.nip` - Foreign key ke pegawai.nik
- `rsia_user_role.id_role` - Foreign key ke rsia_role.id_role

---

## Frontend Integration

### Service File
File: `/src/services/menuService.js`

### Key Methods:
- `accessLevelService.getAllAccessLevels()` - Get all roles
- `pegawaiService.getAllPegawai()` - Get all pegawai with roles
- `pegawaiService.assignAccessLevelToPegawai()` - Assign role to pegawai
- `pegawaiService.removeAccessLevelFromPegawai()` - Remove role from pegawai

### Store File
File: `/src/stores/role.js`

### Key Actions:
- `fetchAllRoles()` - Fetch all roles
- `fetchAllPegawai()` - Fetch all pegawai
- `assignRoleToPegawai()` - Assign role to pegawai
- `removeRoleFromPegawai()` - Remove role from pegawai

---

## Usage Examples

### 1. Get All Users with Their Roles
```javascript
// Using frontend service
const response = await pegawaiService.getAllPegawai();
console.log(response.data); // Array of users with roles
```

### 2. Assign Role to User
```javascript
// Using frontend service
const result = await pegawaiService.assignAccessLevelToPegawai(
  '123456', // NIP
  1,        // Role ID
  1         // User ID
);
```

### 3. Get User Management Statistics
```javascript
// Using frontend service
const stats = await pegawaiService.getPegawaiStatistics();
console.log(stats.data); // Statistics object
```

---

## Notes

1. **Authentication**: All endpoints require `auth:aes` middleware
2. **Soft Delete**: Role assignments use soft delete (is_active flag)
3. **Pagination**: Pegawai list supports pagination
4. **Search**: Pegawai search supports name, NIP, and jabatan
5. **Field Mapping**: Some fields are mapped for frontend compatibility (e.g., nik -> nip)
6. **Data Validation**: All inputs are validated before processing
7. **Error Handling**: Consistent error response format across all endpoints