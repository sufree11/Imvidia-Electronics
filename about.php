<?php
require_once 'db/session.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';

$user = checkCustomerOrGuest();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>About Us - ImVidia</title>
    <?php include 'includes/head.php'; ?>
</head>
<body class="bg-fixed bg-gray-50 text-gray-800 flex flex-col min-h-screen dark:bg-slate-950 dark:text-gray-100" style="background-image: radial-gradient(circle, rgba(156, 163, 175, 0.2) 2.5px, transparent 2.5px); background-size: 40px 40px;">
    <?php include 'includes/navbar-customer.php'; ?>

    <main class="flex-grow max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 w-full">
        <header class="mb-10">
            <h1 class="text-4xl font-bold mb-3 text-imvidia-dark dark:text-imvidia-light">About Us</h1>
            <p class="text-gray-600 dark:text-gray-300 max-w-3xl">
                Learn about how we came to be, our mission and vision, and get help with your questions.
            </p>
        </header>

        <section id="about" class="mb-10 bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl shadow-sm p-6 sm:p-8">
            <h2 class="text-2xl font-bold mb-6 text-gray-900 dark:text-white">About</h2>

            <div class="space-y-8">
                <div>
                    <h3 class="text-lg font-semibold mb-3 text-imvidia-dark dark:text-imvidia-light">Company History</h3>
                    <p class="font-medium italic text-gray-700 dark:text-gray-200 mb-3">"Powering Lives, at a Better Price."</p>
                    <p class="text-gray-600 dark:text-gray-300 leading-relaxed mb-3">
                        Founded in 2015, ImVidia Electronics was established with a clear objective to help people experience everyday life with electrical appliances. The company was created because many people needed products that are reliable, affordable, and built with modern technology.
                    </p>
                    <p class="text-gray-600 dark:text-gray-300 leading-relaxed mb-3">
                        ImVidia started as a small local business focused on household appliances such as basic kitchen equipment. Through customer surveys, the founder learned that many consumers struggled to find appliances that balanced quality and cost, which became the main driver of the company production.
                    </p>
                    <p class="text-gray-600 dark:text-gray-300 leading-relaxed">
                        Today, through strong customer support and company dedication, ImVidia has become a trusted brand that produces high-quality products that enhance comfort. ImVidia Electronics continues expanding its product range to reach more customers and help improve their lives with better appliances.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-gray-50 dark:bg-slate-800/60 border border-gray-200 dark:border-slate-700 rounded-xl p-5">
                        <h3 class="text-lg font-semibold mb-2 text-imvidia-dark dark:text-imvidia-light">Vision</h3>
                        <p class="text-gray-600 dark:text-gray-300 leading-relaxed">
                            To become the leading and trusted brand in the electrical appliances industry by delivering innovative and user-friendly solutions that improve everyday life.
                        </p>
                    </div>
                    <div class="bg-gray-50 dark:bg-slate-800/60 border border-gray-200 dark:border-slate-700 rounded-xl p-5">
                        <h3 class="text-lg font-semibold mb-2 text-imvidia-dark dark:text-imvidia-light">Mission</h3>
                        <p class="text-gray-600 dark:text-gray-300 leading-relaxed">
                            To provide high-quality electrical appliances that meet safety and performance standards, ensure customer satisfaction through excellent service and reliable support, and build long-term relationships with customers, partners, and communities.
                        </p>
                    </div>
                </div>

                <div>
                    <h3 class="text-lg font-semibold mb-3 text-imvidia-dark dark:text-imvidia-light">Organisational Chart</h3>
                    <div class="bg-gray-50 dark:bg-slate-800/60 border border-gray-200 dark:border-slate-700 rounded-xl p-5">
                        <p class="text-gray-600 dark:text-gray-300 leading-relaxed mb-4">
                            ImVidia operates with a founder-led structure focused on product quality, customer support, and operations.
                        </p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-sm">
                            <div class="rounded-lg border border-gray-200 dark:border-slate-700 px-4 py-3 text-center font-medium bg-white dark:bg-slate-900">Founder / Management</div>
                            <div class="rounded-lg border border-gray-200 dark:border-slate-700 px-4 py-3 text-center font-medium bg-white dark:bg-slate-900">Product &amp; Quality</div>
                            <div class="rounded-lg border border-gray-200 dark:border-slate-700 px-4 py-3 text-center font-medium bg-white dark:bg-slate-900">Operations &amp; Logistics</div>
                            <div class="rounded-lg border border-gray-200 dark:border-slate-700 px-4 py-3 text-center font-medium bg-white dark:bg-slate-900">Customer Support</div>
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="text-lg font-semibold mb-3 text-imvidia-dark dark:text-imvidia-light">Location</h3>
                    <div class="bg-gray-50 dark:bg-slate-800/60 border border-gray-200 dark:border-slate-700 rounded-xl p-5">
                        <p class="text-gray-700 dark:text-gray-200 font-medium">Unit 6, 7th Street, New Eridu, 42000, Suibian District, Zen Zone.</p>
                    </div>
                </div>
            </div>
        </section>

        <section id="faq" class="mb-10 bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl shadow-sm p-6 sm:p-8">
            <h2 class="text-2xl font-bold mb-6 text-gray-900 dark:text-white">FAQ</h2>

            <div class="space-y-4">
                <div class="border border-gray-200 dark:border-slate-700 rounded-xl p-4 bg-gray-50 dark:bg-slate-800/60">
                    <h3 class="font-semibold text-gray-900 dark:text-white mb-1">Q: How do I request a refund?</h3>
                    <p class="text-gray-600 dark:text-gray-300">A: No.</p>
                </div>

                <div class="border border-gray-200 dark:border-slate-700 rounded-xl p-4 bg-gray-50 dark:bg-slate-800/60">
                    <h3 class="font-semibold text-gray-900 dark:text-white mb-1">Q: When will my orders arrive?</h3>
                    <p class="text-gray-600 dark:text-gray-300">A: When they are shipped.</p>
                </div>

                <div class="border border-gray-200 dark:border-slate-700 rounded-xl p-4 bg-gray-50 dark:bg-slate-800/60">
                    <h3 class="font-semibold text-gray-900 dark:text-white mb-1">Q: My product came in broken, how do I claim my warranty?</h3>
                    <p class="text-gray-600 dark:text-gray-300">A: The warranty is for decoration purposes only.</p>
                </div>

                <div class="border border-gray-200 dark:border-slate-700 rounded-xl p-4 bg-gray-50 dark:bg-slate-800/60">
                    <h3 class="font-semibold text-gray-900 dark:text-white mb-1">Q: Is this company in any way related to Nvidia?</h3>
                    <p class="text-gray-600 dark:text-gray-300">A: No... Who approved these questions?</p>
                </div>

                <div class="border border-gray-200 dark:border-slate-700 rounded-xl p-4 bg-gray-50 dark:bg-slate-800/60">
                    <h3 class="font-semibold text-gray-900 dark:text-white mb-1">Q: How do I know if a product is good if there is no review system?</h3>
                    <p class="text-gray-600 dark:text-gray-300">A: uhh... Distraction:</p>
                    <a href="https://www.youtube.com/watch?v=J8WId0vbJ3I&list=RDJ8WId0vbJ3I&start_radio=1" target="_blank" rel="noopener noreferrer">
                    <img src="assets/distraction.jpg" alt="Distraction" class="w-1/4 h-auto mt-2 rounded-lg border border-gray-200 dark:border-slate-700 hover:-translate-y-1 hover:shadow-lg duration-300 transition-transform">
                    </a>
                    
                </div>
            </div>
        </section>

        <section id="support" class="bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl shadow-sm p-6 sm:p-8">
            <h2 class="text-2xl font-bold mb-6 text-gray-900 dark:text-white">Support</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-gray-50 dark:bg-slate-800/60 border border-gray-200 dark:border-slate-700 rounded-xl p-5">
                    <h3 class="text-lg font-semibold mb-3 text-imvidia-dark dark:text-imvidia-light">Contact</h3>
                    <p class="text-gray-600 dark:text-gray-300">
                        Support hotline:
                        <span class="font-semibold text-gray-900 dark:text-white">+603 672 1511</span>
                    </p>
                </div>

                <div class="bg-gray-50 dark:bg-slate-800/60 border border-gray-200 dark:border-slate-700 rounded-xl p-5">
                    <h3 class="text-lg font-semibold mb-3 text-imvidia-dark dark:text-imvidia-light">Social Media</h3>
                    <ul class="space-y-2 text-gray-600 dark:text-gray-300">
                        <li><span class="font-semibold text-gray-900 dark:text-white">Instagram:</span> @imvidia_</li>
                        <li><span class="font-semibold text-gray-900 dark:text-white">X:</span> @imvidia</li>
                        <li><span class="font-semibold text-gray-900 dark:text-white">Facebook:</span> imvidia</li>
                        <li><span class="font-semibold text-gray-900 dark:text-white">Twitter:</span> imvidia</li>
                    </ul>
                </div>
            </div>
        </section>
    </main>

    <?php include 'includes/footer.php'; ?>
</body>
</html>