 <?php

    return [
        'modules' => [
            'credentials' => [
                'fields' => [
                    'title' => 'اعتبارنامه‌ها',
                    'headerTitle' => 'مدیریت اعتبارنامه‌ها',
                    'send_rate' => 'نرخ ارسال',
                    'send_unit' => 'واحد نرخ',
                    'helper_name' => 'نام هلپر',
                    'token_type' => 'نوع توکن',
                    'token_status' => 'وضعیت توکن',
                    'daily_limit' => 'محدودیت روزانه',
                    'messages_sent_today' => 'ارسال امروز',
                    'last_token_expires_at' => 'انقضای توکن',
                ],
                'actions' => [
                    'create' => 'ایجاد اعتبارنامه جدید',
                    'edit'   => 'ویرایش اعتبارنامه',
                ],
            ],
            'socials' => [
                'fields' => [
                    'title' => 'پیام رسان ها',
                    'headerTitle' => 'مدیریت پیام رسان ها',
                    'social' => 'پیام رسان',
                    'send_rate' => 'نرخ ارسال',
                    'rate_unit' => 'واحد نرخ',
                ],
                'actions' => [
                    'create' => 'ایجاد پیام رسان جدید',
                    'edit'   => 'ویرایش پیام رسان',
                ],
            ],
            'users' => [
                'fields' => [
                    'title' => 'کاربران',
                    'roles' => 'نقش ',
                    'headerTitle' => 'مدیریت کاربران',
                ],
                'actions' => [
                    'create' => 'ایجاد کاربر جدید',
                    'edit'   => 'ویرایش کاربر',
                ],
            ],
            'roles' => [
                'fields' => [
                    'title' => 'نقش ها',
                    'headerTitle' => 'مدیریت نقش ها',
                ],
                'actions' => [
                    'create' => 'ایجاد نقش جدید',
                    'edit'   => 'ویرایش نقش',
                ],
            ],
            'permissions' => [
                'fields' => [
                    'title' => 'دسترسی ها',
                    'headerTitle' => 'مدیریت دسترسی ها',
                ],
                'actions' => [
                    'create' => 'ایجاد دسترسی جدید',
                    'edit'   => 'ویرایش دسترسی',
                ],
            ],
        ],
        'common' => [
            'url'         => 'آدرس دامنه',
            'address'     => 'آدرس',
            'email'       => 'ایمیل',
            'mail'        => 'ایمیل',
            'status'      => 'وضعیت',
            'id'          => 'شناسه',
            'user_id'     => 'شناسه کاربر',
            'name'        => 'نام',
            'username'    => 'نام کاربری',
            'save'        => 'ذخیره',
            'cancel'      => 'انصراف',
            'active'      => 'فعال',
            'inactive'    => 'غیرفعال',
            'password'    => 'رمز عبور',
            'is_active'   => 'فعال',
            'create'      => 'ایجاد',
            'edit'        => 'ویرایش',
            'site'        => 'سایت',
            'website'     => 'وب سایت',
            'created_at'  => 'تاریخ ساخت',
            'updated_at'  => 'تاریخ بروزرسانی',
            'created_by'  => 'ایجاد کننده',
            'deleted_at'  => 'تاریخ حذف',
            'is_default'  => 'پیش فرض',
            'indefault'   => 'غیر پیش فرض',
            'title'       => 'عنوان',
            'user'        => 'کاربر',
            'item'        => 'آیتم',
            'delete'      => 'حذف',
            'actions'     => 'عملیات',
            'allowed_ip'  => 'آی پی مجاز',
            'manage'      => 'مدیریت',
            'description' => 'توضیحات',
            'token'       => 'توکن',
            'nationalCode' => 'کدملی',
            'national_code' => 'کدملی',
            'all_status' => 'همه وضعیت ها',
        ],
    ];
