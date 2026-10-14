<?php

namespace App\Support;

use RuntimeException;

/** Ảnh tải lên không hợp lệ: thông báo hiển thị được cho người dùng. */
class InvalidImage extends RuntimeException {}
