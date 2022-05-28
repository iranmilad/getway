

<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Laravel Vue</title>
    <!-- Scripts -->
    <script src="{{ asset('js/app.js') }}" defer></script>
    <!-- Fonts -->
    <link rel="dns-prefetch" href="//fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css?family=Nunito" rel="stylesheet">
    <!-- Styles -->
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
</head>
<body>
    <div id="app">
        <main class="py-3">
            <h3>Laravel Vue</h3>

            <div class="col-lg-12">
            <form class="box-search">
                <div class="form-group">
                    <label>گروه بندی</label>
                    <select name="category">
                    <option value="">همه</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>تعداد نمایش</label>
                    <select name="per_page">
                    <option value="10">10</option>
                    <option value="10">20</option>
                    <option value="10">30</option>
                    <option value="10">50</option>
                    <option value="10">100</option>
                    <option value="10">150</option>
                    <option value="10">200</option>
                    <option value=''>همه</option>
                    </select>
                </div>
                <button class="btn btn-success" id="search-match">جستجو</button>
                </form>
                <div class="match">
                <table id="woholoTabel" class="table table-striped table-bordered form-table" style="width:100%">
                    <thead>
                    <tr>
                    <th>کد محصول</th>
                    <th>عنوان محصول</th>
                    <th>موجودی</th>
                    <th>قیمت محصول</th>
                    <th>کد هلو</th>
                    <th>پیام</th>
                    <th>عملیات</th>
                    </tr>
                    </thead>
                </table>
                <div id="ploading">در حال بارگزاری</div>
                </div>

            </div>
            <!-- /.col-->
        </main>
    </div>
    <script src="{{ mix('/js/app.js') }}"></script>
    <script>
        jQuery(document).ready(function() {
            jQuery('#search-match').click(function(e){
            e.preventDefault();
            var category=jQuery('.box-search').find('select[name="category"]').val();
            var per_page=jQuery('.box-search').find('select[name="per_page"]').val();
            jQuery('#woholoTabel').DataTable().clear().destroy();
            jQuery.ajax({
                type:'POST',
                url:wooholo_ajax_obj.ajaxurl,
                data: {
                'action': 'woo_holo',
                'endpoint':'getProductConflict',
                'nonce' : {{ $token }},
                'search_category':category,
                'per_page':per_page
                },
                beforeSend:function () {
                jQuery('.match').addClass('shimmer');
                jQuery('#ploading,.match').show();

                },
                success:function (data) {
                data = jQuery.parseJSON(data);
                if (data.responseCode === 200) {
                    if (Object.keys(data.response.result).length !== 0){
                    var dataTable= jQuery('#woholoTabel').DataTable({
                        "processing": true,
                        "language": getLanguage(),
                        "aaData": data.response.result,
                        "aoColumns": [
                        {'mData': 'woocommerce_product_id'},
                        {'mData': 'product_name'},
                        {'mData': 'amount'},
                        {'mData': 'price'},
                        {
                            'mData': 'holo_code',
                            sortable: false,
                            "render": function (data, type, full, meta) {
                            if ($.inArray(3, full.msg_code) !== -1) {
                                hc = '<p>';
                                hc += '<input value="' + data + '" name="holo_code"/>';
                                hc += '</p>';
                                hc += '<a data-wcid="' + full.woocommerce_product_id + '" class="holoCodeUpdate"><span class="dashicons dashicons-update-alt"></a>';
                                return hc;
                            } else {
                                return data;
                            }
                            }
                        },
                        {
                            'mData': 'msg',

                            sortable: false,
                            "render": function (data, type, full, meta) {
                            var ms = '<ul>';
                            for (var i in full.msg) {
                                var msg = full.msg;
                                ms += '<li>' + msg[i] + '</li>';
                            }
                            ms += '</ul>';
                            return ms;
                            }
                        },
                        {

                            'mData': 'msg_code',
                            sortable: false,
                            "render": function (data, type, full, meta) {
                            if ($.inArray(2, full.msg_code) !== -1 || $.inArray(1, full.msg_code) !== -1 || $.inArray(0, full.msg_code) !== -1) {
                                return '<button data-holoid="' + full.holo_code + '" data-wcid="' + full.woocommerce_product_id + '" class="updatePr"><span class="dashicons dashicons-update-alt"></button>';
                            } else {
                                return '';
                            }

                            }

                        },

                        ]
                    });
                    }
                    else {
                    html = '<div class="alert alert-danger" role="alert">'+data.message+'</div>';
                    jQuery('.match').html(html);
                    }
                }
                else {
                    html = '<div class="alert alert-danger" role="alert">'+data.message+'</div>';
                    jQuery('.match').html(html);
                }
                },
                error: function (request, status, error) {
                alert('Time out');
                },
                complete:function () {
                jQuery('.match').removeClass('shimmer');
                jQuery('#ploading').hide();
                }
            });
            });
        });
    </script>

</body>
</html>





