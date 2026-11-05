<footer class="bg-gray-950 text-gray-400 mt-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-14 pb-8">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-10 mb-10">
            <!-- Brand -->
            <div class="col-span-1 sm:col-span-2 lg:col-span-1">
                <div class="flex items-center gap-2 mb-4">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-purple-500 to-indigo-600 flex items-center justify-center">
                        <i class="bi bi-shop text-white text-sm"></i>
                    </div>
                    <span class="font-bold text-xl text-white"><?php echo APP_NAME; ?></span>
                </div>
                <p class="text-sm leading-relaxed mb-4">Your campus supermarket at BITS Pilani Goa. Snacks, essentials, and more — delivered to your hostel.</p>
                <div class="flex items-center gap-2 text-sm">
                    <i class="bi bi-geo-alt-fill text-purple-400"></i>
                    <span>BITS Pilani Goa Campus, Goa</span>
                </div>
                <div class="flex items-center gap-2 text-sm mt-2">
                    <i class="bi bi-instagram text-purple-400"></i>
                    <a href="https://instagram.com/goofy_kushz" target="_blank" class="hover:text-purple-400 transition-colors">goofy_kushz</a>
                </div>
            </div>

            <!-- Quick Links -->
            <div>
                <h4 class="text-white font-semibold mb-4 text-sm uppercase tracking-wider">Shop</h4>
                <ul class="space-y-2.5 text-sm">
                    <li><a href="/jv_shetty/products.php" class="hover:text-purple-400 transition-colors flex items-center gap-2"><i class="bi bi-chevron-right text-xs text-purple-500"></i>All Products</a></li>
                    <li><a href="/jv_shetty/categories.php" class="hover:text-purple-400 transition-colors flex items-center gap-2"><i class="bi bi-chevron-right text-xs text-purple-500"></i>Categories</a></li>
                    <li><a href="/jv_shetty/products.php?sort=created_at&order=DESC" class="hover:text-purple-400 transition-colors flex items-center gap-2"><i class="bi bi-chevron-right text-xs text-purple-500"></i>New Arrivals</a></li>
                    <li><a href="/jv_shetty/about.php" class="hover:text-purple-400 transition-colors flex items-center gap-2"><i class="bi bi-chevron-right text-xs text-purple-500"></i>About Us</a></li>
                </ul>
            </div>

            <!-- Support -->
            <div>
                <h4 class="text-white font-semibold mb-4 text-sm uppercase tracking-wider">Support</h4>
                <ul class="space-y-2.5 text-sm">
                    <li><a href="/jv_shetty/faq.php" class="hover:text-purple-400 transition-colors flex items-center gap-2"><i class="bi bi-chevron-right text-xs text-purple-500"></i>FAQ</a></li>
                    <li><a href="/jv_shetty/shipping.php" class="hover:text-purple-400 transition-colors flex items-center gap-2"><i class="bi bi-chevron-right text-xs text-purple-500"></i>Shipping Policy</a></li>
                    <li><a href="/jv_shetty/returns.php" class="hover:text-purple-400 transition-colors flex items-center gap-2"><i class="bi bi-chevron-right text-xs text-purple-500"></i>Returns</a></li>
                    <li><a href="/jv_shetty/privacy.php" class="hover:text-purple-400 transition-colors flex items-center gap-2"><i class="bi bi-chevron-right text-xs text-purple-500"></i>Privacy Policy</a></li>
                    <li><a href="/jv_shetty/contact.php" class="hover:text-purple-400 transition-colors flex items-center gap-2"><i class="bi bi-chevron-right text-xs text-purple-500"></i>Contact Us</a></li>
                </ul>
            </div>

            <!-- Store Hours -->
            <div>
                <h4 class="text-white font-semibold mb-4 text-sm uppercase tracking-wider">Store Hours</h4>
                <div class="space-y-3 text-sm">
                    <div class="flex items-center gap-3 p-3 rounded-xl bg-gray-900 border border-gray-800">
                        <i class="bi bi-clock text-purple-400 text-base"></i>
                        <div>
                            <p class="text-white font-medium">Daily</p>
                            <p class="text-gray-400">4:00 PM – 9:00 PM</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 p-3 rounded-xl bg-gray-900 border border-gray-800">
                        <i class="bi bi-truck text-purple-400 text-base"></i>
                        <div>
                            <p class="text-white font-medium">Campus Delivery</p>
                            <p class="text-gray-400">Within store hours</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="border-t border-gray-800 pt-6 flex flex-col sm:flex-row items-center justify-between gap-3 text-sm">
            <p>© <?php echo date('Y'); ?> JV Shetty. Demo website by Kushagra.</p>
            <p class="flex items-center gap-1.5 text-gray-500">
                Made with <i class="bi bi-heart-fill text-red-500 text-xs"></i> for BITSians
            </p>
        </div>
    </div>
</footer>
