# Role-Menu Management API Documentation

## Overview
API endpoints untuk mengelola hubungan antara role dan menu dengan permissions (hak akses). Sistem ini mengizinkan admin untuk mengatur akses setiap role ke menu-menu yang tersedia.

## Base URL
```
/api/menu-management
```

## Authentication
Semua endpoints memerlukan middleware `auth:aes`.

---

## Master Menu Management

### Get All Menus
```http
GET /api/menu-management/menus
```

**Query Parameters:**
- `active` (boolean, optional): Filter by active status
- `search` (string, optional): Search by nama_menu or route
- `parent_id` (integer, optional): Filter by parent ID

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id_menu": 1,
      "nama_menu": "Dashboard",
      "icon": "fas fa-tachometer-alt",
      "route": "/dashboard",
      "parent_id": null,
      "urutan": 1,
      "is_active": true,
      "parent": null,
      "children": [...]
    }
  ],
  "total": 1
}
```

### Get Menu Tree
```http
GET /api/menu-management/menus/tree
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id_menu": 1,
      "nama_menu": "Dashboard",
      "icon": "fas fa-tachometer-alt",
      "route": "/dashboard",
      "children": [
        {
          "id_menu": 2,
          "nama_menu": "Analytics",
          "icon": "fas fa-chart-line",
          "route": "/dashboard/analytics",
          "parent_id": 1,
          "children": []
        }
      ]
    }
  ]
}
```

### Create Menu
```http
POST /api/menu-management/menus
```

**Request Body:**
```json
{
  "nama_menu": "Menu Name",
  "icon": "fas fa-icon",
  "route": "/route",
  "parent_id": null,
  "urutan": 1,
  "is_active": true
}
```

### Update Menu
```http
PUT /api/menu-management/menus/{id}
```

### Delete Menu
```http
DELETE /api/menu-management/menus/{id}
```

### Reorder Menus
```http
POST /api/menu-management/menus/reorder
```

**Request Body:**
```json
{
  "menu_order": [
    {
      "id_menu": 1,
      "urutan": 1
    },
    {
      "id_menu": 2,
      "urutan": 2
    }
  ]
}
```

---

## Role-Menu Permission Management

### Get Role Menu Summary
```http
GET /api/menu-management/role-permissions/summary
```

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
      "role_menus_count": 10,
      "permission_count": 15
    }
  ]
}
```

### Get Role Permissions (with menu tree)
```http
GET /api/menu-management/role-permissions/role/{roleId}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "role": {
      "id_role": 1,
      "nama_role": "Admin",
      "deskripsi": "Administrator role"
    },
    "menus": [
      {
        "id_menu": 1,
        "nama_menu": "Dashboard",
        "permissions": {
          "can_view": true,
          "can_create": false,
          "can_update": false,
          "can_delete": false,
          "can_export": false,
          "can_import": false
        },
        "children": [...]
      }
    ],
    "permissions": {
      "1": {
        "id_role_menu": 1,
        "id_role": 1,
        "id_menu": 1,
        "can_view": true,
        "can_create": false,
        "can_update": false,
        "can_delete": false,
        "can_export": false,
        "can_import": false
      }
    }
  }
}
```

### Get Role Menu Details (flat list)
```http
GET /api/menu-management/role-permissions/role/{roleId}/details
```

**Response:**
```json
{
  "success": true,
  "data": {
    "role": {
      "id_role": 1,
      "nama_role": "Admin"
    },
    "permissions": [
      {
        "id_role_menu": 1,
        "id_role": 1,
        "id_menu": 1,
        "nama_menu": "Dashboard",
        "route": "/dashboard",
        "can_view": true,
        "can_create": false,
        "can_update": false,
        "can_delete": false,
        "can_export": false,
        "can_import": false
      }
    ]
  }
}
```

### Update Role Permissions
```http
POST /api/menu-management/role-permissions/role/{roleId}
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
    },
    {
      "id_menu": 2,
      "can_view": true,
      "can_create": true,
      "can_update": true,
      "can_delete": false,
      "can_export": true,
      "can_import": false
    }
  ]
}
```

### Copy Role Permissions
```http
POST /api/menu-management/role-permissions/copy
```

**Request Body:**
```json
{
  "source_role_id": 1,
  "target_role_id": 2
}
```

**Response:**
```json
{
  "success": true,
  "message": "Permissions copied successfully",
  "copied_count": 15
}
```

### Check User Access
```http
POST /api/menu-management/role-permissions/check-access
```

**Request Body:**
```json
{
  "menu_id": 1,
  "permission": "can_view",
  "user_id": "123456" // optional, defaults to authenticated user
}
```

**Response:**
```json
{
  "success": true,
  "has_access": true
}
```

---

## User Menu Access

### Get User Menus
```http
GET /api/menu-management/user-menus
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id_menu": 1,
      "nama_menu": "Dashboard",
      "icon": "fas fa-tachometer-alt",
      "route": "/dashboard",
      "children": [
        {
          "id_menu": 2,
          "nama_menu": "Analytics",
          "route": "/dashboard/analytics",
          "children": []
        }
      ]
    }
  ],
  "user_role": {
    "id_role": 1,
    "nama_role": "Admin"
  }
}
```

---

## Permissions Available

| Permission | Description |
|------------|-------------|
| `can_view` | View menu/item |
| `can_create` | Create new data |
| `can_update` | Edit/update existing data |
| `can_delete` | Delete data |
| `can_export` | Export data to various formats |
| `can_import` | Import data from files |

---

## Database Schema Reference

### Tables Used:
- `rsia_menu` - Master menu data
- `rsia_role` - Role definitions
- `rsia_role_menu` - Role-menu permissions mapping

### Key Fields:
- `rsia_menu.id_menu` - Primary key untuk menu
- `rsia_role.id_role` - Primary key untuk role
- `rsia_role_menu.id_role_menu` - Primary key untuk mapping
- `rsia_role_menu.id_role` - Foreign key ke rsia_role
- `rsia_role_menu.id_menu` - Foreign key ke rsia_menu

---

## Frontend Integration

### Service File
File: `/src/services/menuService.js`

### Key Methods:
```javascript
// Menu Management
menuService.getAllMenus()
menuService.getMenuTree()
menuService.createMenu(menuData)
menuService.updateMenu(id, menuData)
menuService.deleteMenu(id)
menuService.reorderMenus(menuOrder)

// Role-Menu Permissions
menuService.getRoleMenuSummary()
menuService.getRolePermissions(roleId)
menuService.getRoleMenuDetails(roleId)
menuService.updateRolePermissions(roleId, permissions)
menuService.copyRolePermissions(sourceRoleId, targetRoleId)
menuService.checkUserAccess(menuId, permission)

// User Menus
menuService.getUserMenus()
```

### Store Integration
Gunakan dengan `role.js` store untuk mengelola state role dan permissions.

---

## Usage Examples

### 1. Get All Menus with Tree Structure
```javascript
const response = await menuService.getMenuTree();
const menuTree = response.data;
// menuTree contains hierarchical menu structure
```

### 2. Update Role Permissions
```javascript
const permissions = [
  {
    id_menu: 1,
    can_view: true,
    can_create: true,
    can_update: true,
    can_delete: false,
    can_export: true,
    can_import: false
  }
];

const result = await menuService.updateRolePermissions(1, permissions);
```

### 3. Check User Access
```javascript
const hasAccess = await menuService.checkUserAccess(1, 'can_create');
if (hasAccess.has_access) {
  // User can create data in menu with ID 1
}
```

### 4. Copy Permissions Between Roles
```javascript
const result = await menuService.copyRolePermissions(1, 2);
console.log(`Copied ${result.copied_count} permissions`);
```

---

## Error Handling

### Common Error Responses:

**Validation Error (422):**
```json
{
  "success": false,
  "error": "The given data was invalid.",
  "errors": {
    "nama_menu": ["The nama menu field is required."]
  }
}
```

**Not Found (404):**
```json
{
  "success": false,
  "error": "Menu not found"
}
```

**Circular Reference (422):**
```json
{
  "success": false,
  "error": "Circular reference detected in menu hierarchy"
}
```

**Permission Denied (422):**
```json
{
  "success": false,
  "error": "Cannot delete menu with role assignments"
}
```

---

## Security Considerations

1. **Authentication**: Semua endpoints memerlukan `auth:aes` middleware
2. **Authorization**: Hanya user dengan role yang sesuai yang bisa akses
3. **Input Validation**: Semua input divalidasi sebelum processing
4. **Database Integrity**: Foreign key constraints ensure data integrity
5. **Soft Delete**: Role assignments menggunakan soft delete untuk audit trail

---

## Performance Considerations

1. **Menu Tree**: Gunakan caching untuk menu tree yang sering diakses
2. **Permissions**: Batch updates untuk multiple permissions lebih efisien
3. **Database Index**: Index pada foreign key fields untuk query performance
4. **Lazy Loading**: Load children menus hanya saat diperlukan

---

## Features Ready

✅ **Complete CRUD**: Create, read, update, delete menus
✅ **Hierarchical Structure**: Support parent-child menu relationships
✅ **Permission Management**: 6 types of permissions per menu
✅ **Role-Based Access**: Assign permissions per role
✅ **Copy Permissions**: Copy permissions between roles
✅ **Access Checking**: Real-time access validation
✅ **User Menus**: Dynamic menu generation based on user roles
✅ **Bulk Operations**: Reorder menus, bulk permission updates
✅ **Security**: Comprehensive validation and error handling