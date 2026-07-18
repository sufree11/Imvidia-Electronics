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
<body class="bg-gray-50 text-gray-800 flex flex-col min-h-screen dark:bg-slate-950 dark:text-gray-100">
    <?php include 'includes/navbar-customer.php'; ?>

    <main class="flex-grow max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 w-full animate-fade-in-up">
        <header class="mb-10">
            <h1 class="text-4xl font-bold mb-3 text-imvidia-dark dark:text-imvidia-light">About Us</h1>
            <p class="text-gray-600 dark:text-gray-300 max-w-3xl">
                Learn about how we came to be, our mission and vision, and see our FAQ.
            </p>
        </header>

        <section id="about" class="mb-10 bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl shadow-sm p-6 sm:p-8">
            <h2 class="text-2xl font-bold mb-6 text-gray-900 dark:text-white flex items-center gap-3">
                <img class="theme-logo h-10 w-10 rounded-full object-cover border border-gray-200 dark:border-slate-700 bg-white p-1" data-light="assets/logo.svg" data-dark="assets/logo-light.svg" src="assets/logo.svg" alt="ImVidia Logo">
                About ImVidia
            </h2>

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
                    <h3 class="text-lg font-semibold mb-3 text-imvidia-dark dark:text-imvidia-light">Organisational Structure</h3>
                    <div class="bg-gray-50 dark:bg-slate-800/60 border border-gray-200 dark:border-slate-700 rounded-xl p-5">
                        <p class="text-gray-600 dark:text-gray-300 leading-relaxed mb-4">
                            ImVidia operates with a structure focused on product quality, customer support, and operations.
                        </p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-sm">
                            <div class="rounded-lg border border-gray-200 dark:border-slate-700 px-4 py-3 text-center font-medium bg-white dark:bg-slate-900">
                                <img src="assets/founder.png" alt="Founder / Management" class="w-32 h-32 mx-auto mb-2 rounded-full border border-gray-200 dark:border-slate-700">
                                <b>Mohammad Imran Shakir</b> <br> Founder / Management
                            </div>
                            <div class="rounded-lg border border-gray-200 dark:border-slate-700 px-4 py-3 text-center font-medium bg-white dark:bg-slate-900">
                                <img src="assets/quality.png" alt="Product & Quality" class="w-32 h-32 mx-auto mb-2 rounded-full border border-gray-200 dark:border-slate-700">
                                <b>Putera Mikhail Fallon</b> <br> Product &amp; Quality
                            </div>
                            <div class="rounded-lg border border-gray-200 dark:border-slate-700 px-4 py-3 text-center font-medium bg-white dark:bg-slate-900">
                                <img src="assets/operations.png" alt="Operations & Logistics" class="w-32 h-32 mx-auto mb-2 rounded-full border border-gray-200 dark:border-slate-700">
                                <b>Mohammad Sufree</b> <br> Operations &amp; Logistics
                            </div>
                            <div class="rounded-lg border border-gray-200 dark:border-slate-700 px-4 py-3 text-center font-medium bg-white dark:bg-slate-900">
                                <img src="assets/support.png" alt="Customer Support" class="w-32 h-32 mx-auto mb-2 rounded-full border border-gray-200 dark:border-slate-700">
                                <b>Muhammad Firas Faiq</b> <br> Customer Support
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="text-lg font-semibold mb-3 text-imvidia-dark dark:text-imvidia-light">Location</h3>
                    <div class="bg-gray-50 dark:bg-slate-800/60 border border-gray-200 dark:border-slate-700 rounded-xl p-5">
                        <p class="text-gray-700 dark:text-gray-200 font-medium">PT 3576, Jalan Engku Sar, 20300 Kuala Terengganu.</p>
                    </div>
                </div>
            </div>
        </section>

        <section id="faq" class="mb-10 bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl shadow-sm p-6 sm:p-8">
            <h2 class="text-2xl font-bold mb-6 text-gray-900 dark:text-white">FAQ</h2>

            <div class="space-y-4">
                <div class="border border-gray-200 dark:border-slate-700 rounded-xl p-4 bg-gray-50 dark:bg-slate-800/60">
                    <h3 class="font-semibold text-gray-900 dark:text-white mb-1">Q: How do I request a refund?</h3>
                    <p class="text-gray-600 dark:text-gray-300">A: Contact our support team and they will issue a refund as long as you have valid reasons. Our team will also issue refunds for any cancelled orders if the mistake was on our end.</p>
                </div>

                <div class="border border-gray-200 dark:border-slate-700 rounded-xl p-4 bg-gray-50 dark:bg-slate-800/60">
                    <h3 class="font-semibold text-gray-900 dark:text-white mb-1">Q: When will my orders arrive?</h3>
                    <p class="text-gray-600 dark:text-gray-300">A: You can check the estimated delivery date on your order history. If your order takes longer than expected to arrive, please reach out to us through our support number or support mail.</p>
                </div>

                <div class="border border-gray-200 dark:border-slate-700 rounded-xl p-4 bg-gray-50 dark:bg-slate-800/60">
                    <h3 class="font-semibold text-gray-900 dark:text-white mb-1">Q: My product came in broken, how do I claim my warranty?</h3>
                    <p class="text-gray-600 dark:text-gray-300">A: You can claim our warranty by contacting our support team with proof of purchase and photos of the damaged product. They will guide you through the warranty claim process.</p>
                </div>

                <div class="border border-gray-200 dark:border-slate-700 rounded-xl p-4 bg-gray-50 dark:bg-slate-800/60">
                    <h3 class="font-semibold text-gray-900 dark:text-white mb-1">Q: What do I do if my order was cancelled by an admin?</h3>
                    <p class="text-gray-600 dark:text-gray-300">A: Refer to the cancellation reason, and if the reason was on our end we will issue a refund or replace your order as soon as the problem is resolved. Else, please contact our support team for further assistance.</p>
                </div>

                <div class="border border-gray-200 dark:border-slate-700 rounded-xl p-4 bg-gray-50 dark:bg-slate-800/60">
                    <h3 class="font-semibold text-gray-900 dark:text-white mb-1">Q: Is this company in any way related to Nvidia?</h3>
                    <p class="text-gray-600 dark:text-gray-300">A: ...</p>
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
                        <span class="font-semibold text-gray-900 dark:text-white">+60 17 3466427</span> <br>
                        Support email:
                        <span class="font-semibold text-gray-900 dark:text-white">imvidia.social@gmail.com</span>
                    </p>
                </div>

                <div class="bg-gray-50 dark:bg-slate-800/60 border border-gray-200 dark:border-slate-700 rounded-xl p-5">
                    <h3 class="text-lg font-semibold mb-3 text-imvidia-dark dark:text-imvidia-light">Social Media</h3>
                    <ul class="space-y-2 text-gray-600 dark:text-gray-300">
                        <li><a href="https://www.instagram.com/imvidia.social" class="text-gray-600 dark:text-gray-300 hover:text-imvidia transition-colors"><i class="fab fa-instagram w-4 h-auto"></i> : @imvidia.social</a></li>
                        <li><a href="https://x.com/imvidia_" class="text-gray-600 dark:text-gray-300 hover:text-imvidia transition-colors"><i class="fab fa-twitter w-4 h-auto"></i> / X : @imvidia_</a></li>
                        <li><a href="https://web.facebook.com/profile.php?id=61591858356667" class="text-gray-600 dark:text-gray-300 hover:text-imvidia transition-colors"><i class="fab fa-facebook w-4 h-auto"></i> : Imvidia Electronics</a></li>
                        <li><a href="https://www.tiktok.com/@imvidia.social" class="text-gray-600 dark:text-gray-300 hover:text-imvidia transition-colors"><i class="fab fa-tiktok w-4 h-auto"></i> : @imvidia.social</a></li>
                    </ul>
                </div>
            </div>
        </section>
    </main>

    <?php include 'includes/footer.php'; ?>
</body>
</html>