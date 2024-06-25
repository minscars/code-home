<?php
require_once 'constants.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Nhận dữ liệu JSON từ yêu cầu POST
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    $json_data_client = file_get_contents(CLIENT_SECURITY_CODE_FILE);
    $json_data_server = file_get_contents(SERVER_SECURITY_CODE_FILE);

    $data_client = json_decode($json_data_client, true);
    $data_server = json_decode($json_data_server, true);

    $action = htmlspecialchars($data['action']);
    $security_client = $data['securityCode'];

    $response = array();

    if ($data_client[SECURITY_CODE_KEY] == $data_server[SECURITY_CODE_KEY]) {
        $json_data_customer = file_get_contents(CUSTOMER_DATA_FILE);
        $data_customer = json_decode($json_data_customer, true);
        $customers = &$data_customer['shareholder_setting'];

        switch ($action) {
            case 'search':
                $customer_id = $data['customerId'];
                $minAge = $data['minAge'];
                $maxAge = $data['maxAge'];
                $city = $data['city'];
                $state = $data['state'];
                $page = isset($data['page']) ? (int)$data['page'] : 1;
                $limit = isset($data['limit']) ? (int)$data['limit'] : 10;
                $offset = ($page - 1) * $limit;

                $results = [];
                $total_customers = 0;

                foreach ($customers as $customer) {
                    $customer_valid = true;

                    // Kiểm tra từng điều kiện tìm kiếm
                    if (!empty($customer_id) && $customer['id'] != $customer_id) {
                        $customer_valid = false;
                    }
                    if (!empty($minAge) && $customer['age'] < $minAge) {
                        $customer_valid = false;
                    }
                    if (!empty($maxAge) && $customer['age'] > $maxAge) {
                        $customer_valid = false;
                    }
                    if (!empty($city) && strcasecmp($customer['address']['city'], $city) != 0) {
                        $customer_valid = false;
                    }
                    if (!empty($state) && strcasecmp($customer['address']['state'], $state) != 0) {
                        $customer_valid = false;
                    }
                    if ($customer_valid && $customer['delete_flag'] == 0) {
                        if ($total_customers >= $offset && $total_customers < $offset + $limit) {
                            $results[] = $customer;
                        }
                        $total_customers++;
                    }
                }

                $total_pages = ceil($total_customers / $limit);

                if (count($results) > 0) {
                    $response['status'] = SUCCESS_STATUS;
                    $response['data'] = $results;
                    $response['total_pages'] = $total_pages;
                    $response['current_page'] = $page;
                } else {
                    $response['status'] = 'no_results';
                    $response['message'] = 'No customers found matching the criteria.';
                }
                break;

            case 'add':
                $newName = htmlspecialchars($data['name']);
                $newAge = htmlspecialchars($data['age']);
                $newEmail = htmlspecialchars($data['email']);
                $newStreet = htmlspecialchars($data['street']);
                $newCity = htmlspecialchars($data['city']);
                $newState = htmlspecialchars($data['state']);
                $newPostalCode = htmlspecialchars($data['postalCode']);
                $newPhone = htmlspecialchars($data['phone']);

                // Tạo ID mới cho khách hàng
                $newId = end($customers)['id'] + 1;

                // Tạo đối tượng khách hàng mới
                $newCustomer = array(
                    'id' => $newId,
                    'name' => $newName,
                    'age' => $newAge,
                    'email' => $newEmail,
                    'address' => array(
                        'street' => $newStreet,
                        'city' => $newCity,
                        'state' => $newState,
                        'postalCode' => $newPostalCode
                    ),
                    'phoneNumbers' => $newPhone,
                    'delete_flag' => 0
                );

                // Thêm khách hàng mới vào danh sách
                $customers[] = $newCustomer;

                // Ghi dữ liệu cập nhật vào tệp JSON
                $result = file_put_contents(CUSTOMER_DATA_FILE, json_encode($data_customer));
                if ($result !== false) {
                    $response['status'] = SUCCESS_STATUS;
                    $response['message'] = 'Customer added successfully.';
                } else {
                    $response['status'] = ERROR_STATUS;
                    $response['message'] = 'Failed to write to the customer file.';
                }
                break;

            case 'update':
                $updateId = htmlspecialchars($data['id']);
                $updateName = htmlspecialchars($data['name']);
                $updateAge = htmlspecialchars($data['age']);
                $updateEmail = htmlspecialchars($data['email']);
                $updateStreet = htmlspecialchars($data['street']);
                $updateCity = htmlspecialchars($data['city']);
                $updateState = htmlspecialchars($data['state']);
                $updatePostalCode = htmlspecialchars($data['postalCode']);
                $updatePhone = htmlspecialchars($data['phone']);

                $customer_found = false;

                foreach ($customers as &$customer) {
                    if ($customer['id'] == $updateId) {
                        $customer['name'] = $updateName;
                        $customer['age'] = $updateAge;
                        $customer['email'] = $updateEmail;
                        $customer['address']['street'] = $updateStreet;
                        $customer['address']['city'] = $updateCity;
                        $customer['address']['state'] = $updateState;
                        $customer['address']['postalCode'] = $updatePostalCode;
                        $customer['phoneNumbers'] = $updatePhone;
                        $customer_found = true;
                        break;
                    }
                }

                if ($customer_found) {
                    // Ghi dữ liệu cập nhật vào tệp JSON
                    $result = file_put_contents(CUSTOMER_DATA_FILE, json_encode($data_customer));
                    if ($result !== false) {
                        $response['status'] = SUCCESS_STATUS;
                        $response['message'] = 'Customer updated successfully.';
                    } else {
                        $response['status'] = ERROR_STATUS;
                        $response['message'] = 'Failed to write to the customer file.';
                    }
                } else {
                    $response['status'] = NOT_FOUND_STATUS;
                    $response['message'] = 'Customer not found.';
                }
                break;

            case 'delete':
                $deleteId = htmlspecialchars($data['id']);

                $customer_found = false;

                foreach ($customers as &$customer) {
                    if ($customer['id'] == $deleteId) {
                        $customer['delete_flag'] = 1;
                        $customer_found = true;
                        break;
                    }
                }

                if ($customer_found) {
                    // Ghi dữ liệu cập nhật vào tệp JSON
                    $result = file_put_contents(CUSTOMER_DATA_FILE, json_encode($data_customer));
                    if ($result !== false) {
                        $response['status'] = SUCCESS_STATUS;
                        $response['message'] = 'Customer deleted successfully.';
                    } else {
                        $response['status'] = ERROR_STATUS;
                        $response['message'] = 'Failed to write to the customer file.';
                    }
                } else {
                    $response['status'] = NOT_FOUND_STATUS;
                    $response['message'] = 'Customer not found.';
                }
                break;

            default:
                $response['status'] = INVALID_ACTION_STATUS;
                $response['message'] = 'Invalid action specified.';
                break;
        }
    } else {
        $response['status'] = INVALID_SECURITY_STATUS;
        $response['message'] = 'Invalid Security.';
    }
} else {
    $response['status'] = INVALID_METHOD_STATUS;
    $response['message'] = 'Invalid request method.';
}

header('Content-Type: application/json');
echo json_encode($response);
?>
