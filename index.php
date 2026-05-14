<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Refúgio do Cuscuzeiro - Chácara de aluguel por temporada em Analândia, São Paulo. Piscina, suítes, churrasqueira e muito mais.">
    <meta name="keywords" content="chácara, aluguel, temporada, Analândia, piscina, refúgio">
    <meta property="og:title" content="Refúgio do Cuscuzeiro - Chácara de Aluguel">
    <meta property="og:description" content="Seu refúgio perfeito em Analândia. Natureza, conforto e exclusividade.">
    <meta property="og:type" content="website">
    
    <title>Refúgio do Cuscuzeiro - Chácara de Aluguel por Temporada</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
</head>
<body>
    <!-- HEADER STICKY -->
    <header class="header">
        <div class="header-container">
            <div class="logo-section">
                <img src="assets/images/logo_transparente.png" alt="Refúgio do Cuscuzeiro Logo" class="logo-img">
            </div>
            
            <nav class="navigation">
                <button class="menu-toggle" id="menuToggle">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
                <ul class="nav-menu" id="navMenu">
                    <li><a href="#chacara" class="nav-link">A Chácara</a></li>
                    <li><a href="#galeria" class="nav-link">Fotos</a></li>
                    <li><a href="#anlandia" class="nav-link">Analândia</a></li>
                    <li><a href="#localizacao" class="nav-link">Localização</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <!-- HERO SECTION -->
    <section class="hero" id="chacara">
        <div class="hero-background">
            <img src="https://images.unsplash.com/photo-1628745277994-2b266f9e8de4?w=1200&h=600&fit=crop" alt="Refúgio do Cuscuzeiro" class="hero-img">
            <div class="hero-overlay"></div>
        </div>
        <div class="hero-content">
            <h2 class="hero-title">Bem-vindo ao Refúgio do Cuscuzeiro</h2>
            <p class="hero-subtitle">Natureza, conforto e exclusividade em um único lugar</p>
            <button class="cta-button" onclick="scrollToComodidades()">Conheça Nossas Comodidades</button>
        </div>
    </section>

    <!-- COMODIDADES SECTION -->
    <section class="comodidades" id="comodidades">
        <div class="section-container">
            <h2 class="section-title">Nossas Comodidades</h2>
            <p class="section-subtitle">Tudo que você precisa para uma estadia inesquecível</p>
            
            <div class="comodidades-grid">
                <div class="comodidade-card">
                    <div class="comodidade-icon">
                        <i class="fas fa-door-open"></i>
                    </div>
                    <h3>4 Suítes</h3>
                    <p>Quartos espaçosos e confortáveis para sua família</p>
                </div>

                <div class="comodidade-card">
                    <div class="comodidade-icon">
                        <i class="fas fa-water"></i>
                    </div>
                    <h3>Piscina</h3>
                    <p>Piscina bem cuidada para refrescantes mergulhos</p>
                </div>

                <div class="comodidade-card">
                    <div class="comodidade-icon">
                        <i class="fas fa-dice"></i>
                    </div>
                    <h3>Salão de Jogos</h3>
                    <p>Diversão garantida para toda a família</p>
                </div>

                <div class="comodidade-card">
                    <div class="comodidade-icon">
                        <i class="fas fa-car"></i>
                    </div>
                    <h3>Garagem Coberta</h3>
                    <p>Espaço seguro para 4 veículos</p>
                </div>

                <div class="comodidade-card">
                    <div class="comodidade-icon">
                        <i class="fas fa-futbol"></i>
                    </div>
                    <h3>Campinho de Futebol</h3>
                    <p>Diversão ao ar livre para os esportistas</p>
                </div>

                <div class="comodidade-card">
                    <div class="comodidade-icon">
                        <i class="fas fa-square"></i>
                    </div>
                    <h3>Quadra de Areia</h3>
                    <p>Perfeita para vôlei e outros esportes</p>
                </div>

                <div class="comodidade-card">
                    <div class="comodidade-icon">
                        <i class="fas fa-fire"></i>
                    </div>
                    <h3>Churrasqueira</h3>
                    <p>Churrascarias memoráveis com amigos</p>
                </div>

                <div class="comodidade-card">
                    <div class="comodidade-icon">
                        <i class="fas fa-spa"></i>
                    </div>
                    <h3>Hidromassagem</h3>
                    <p>Relaxamento total após dias de diversão</p>
                </div>
            </div>
        </div>
    </section>

    <!-- GALERIA DE FOTOS -->
    <section class="galeria" id="galeria">
        <div class="section-container">
            <h2 class="section-title">Galeria de Fotos</h2>
            <p class="section-subtitle">Conheça cada detalhe da nossa chácara</p>
            
            <div class="galeria-grid">
                <div class="galeria-item item-1">
                    <img src="assets/images/IMG_3881.JPG" alt="Piscina e Área de Lazer">
                    <div class="galeria-overlay">
                        <p>Piscina & Lazer</p>
                    </div>
                </div>

                <div class="galeria-item item-2">
                    <img src="assets/images/IMG_3903.JPG" alt="Interior da Chácara">
                    <div class="galeria-overlay">
                        <p>Interior Elegante</p>
                    </div>
                </div>

                <div class="galeria-item item-3">
                    <img src="assets/images/IMG_4270.JPG" alt="Área Externa">
                    <div class="galeria-overlay">
                        <p>Área Externa</p>
                    </div>
                </div>

                <div class="galeria-item item-4">
                    <img src="assets/images/IMG_4293.JPG" alt="Suíte Luxuosa">
                    <div class="galeria-overlay">
                        <p>Suítes Confortáveis</p>
                    </div>
                </div>

                <div class="galeria-item item-5">
                    <img src="assets/images/IMG_8253.jpg" alt="Jardim">
                    <div class="galeria-overlay">
                        <p>Jardim Florido</p>
                    </div>
                </div>

                <div class="galeria-item item-6">
                    <img src="assets/images/12b7a9e2-ba4d-47d4-b492-13eff56428ba.JPG" alt="Áreas Comuns">
                    <div class="galeria-overlay">
                        <p>Áreas Comuns</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- SEÇÃO ANALÂNDIA -->
    <section class="anlandia" id="anlandia">
        <div class="section-container">
            <h2 class="section-title">Explore Analândia</h2>
            <p class="section-subtitle">Descubra a beleza e os atrativos da região</p>
            
            <div class="cards-grid">
                <div class="explore-card">
                    <div class="card-image">
                        <img src="https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=400&h=300&fit=crop" alt="Cuscuzeiro">
                        <span class="card-badge">Natureza</span>
                    </div>
                    <div class="card-content">
                        <h3>Cuscuzeiro</h3>
                        <p>Formação geológica única com paisagens espetaculares. Um passeio imperdível para apreciar a natureza selvagem de Analândia.</p>
                    </div>
                </div>

                <div class="explore-card">
                    <div class="card-image">
                        <img src="https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=400&h=300&fit=crop" alt="Cachoeiras">
                        <span class="card-badge">Aventura</span>
                    </div>
                    <div class="card-content">
                        <h3>Cachoeiras</h3>
                        <p>Diversos pontos de queda d'água perfeitos para banhos refrescantes e fotos inesquecíveis. Ideal para trilhas e contato com a natureza.</p>
                    </div>
                </div>

                <div class="explore-card">
                    <div class="card-image">
                        <img src="https://images.unsplash.com/photo-1469022563149-aa64dbd37dae?w=400&h=300&fit=crop" alt="Ecoturismo">
                        <span class="card-badge">Ecologia</span>
                    </div>
                    <div class="card-content">
                        <h3>Ecoturismo</h3>
                        <p>Trilhas ecológicas pela Serra Geral, observação de fauna e flora regional. Uma experiência enriquecedora em harmonia com a natureza.</p>
                    </div>
                </div>

                <div class="explore-card">
                    <div class="card-image">
                        <img src="https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=400&h=300&fit=crop" alt="Gastronomia">
                        <span class="card-badge">Cultura</span>
                    </div>
                    <div class="card-content">
                        <h3>Gastronomia Local</h3>
                        <p>Prove os sabores da culinária caipira. Restaurantes e bares com comidas típicas e autênticas que refletem a cultura da região.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- LOCALIZAÇÃO - GOOGLE MAPS -->
    <section class="localizacao" id="localizacao">
        <div class="section-container">
            <h2 class="section-title">Localização</h2>
            <p class="section-subtitle">Encontre-nos facilmente em Analândia</p>
            
            <div class="mapa-container">
                <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3658.9029382895996!2d-47.6694005!3d-22.1337652!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x94b9d6c5c5c5c5c5%3A0x5c5c5c5c5c5c5c5c!2sRefúgio%20do%20Cuscuzeiro!5e0!3m2!1spt-BR!2sbr!4v1715701800000" width="100%" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
            </div>

            <div class="info-contact">
                <div class="info-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <div>
                        <h4>Endereço</h4>
                        <p>Analândia, São Paulo, Brasil</p>
                    </div>
                </div>
                <div class="info-item">
                    <i class="fas fa-phone"></i>
                    <div>
                        <h4>Contato</h4>
                        <p>(16) 99621-2350</p>
                    </div>
                </div>
                <div class="info-item">
                    <i class="fas fa-envelope"></i>
                    <div>
                        <h4>Email</h4>
                        <p>refugiodocuscuzeiro@gmail.com</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FOOTER -->
    <footer class="footer">
        <div class="section-container">
            <p>&copy; 2024 Refúgio do Cuscuzeiro. Todos os direitos reservados.</p>
            <p>Desenvolvido com <i class="fas fa-heart"></i> para sua comodidade</p>
        </div>
    </footer>

    <!-- BOTÕES FLUTUANTES (CALL TO ACTION) -->
    <div class="floating-buttons-container">
        <!-- WhatsApp -->
        <div class="float-button-wrapper" data-tooltip="Fale conosco no WhatsApp">
            <a href="https://wa.me/message/VAHOR3ECX675N1" target="_blank" rel="noopener noreferrer" class="float-btn float-whatsapp" aria-label="Contato via WhatsApp">
                <span class="float-ping"></span>
                <span class="float-glow"></span>
                <svg viewBox="0 0 24 24" fill="currentColor" class="float-icon">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
                </svg>
            </a>
            <div class="float-tooltip">Fale conosco no WhatsApp</div>
        </div>

        <!-- Booking -->
        <div class="float-button-wrapper" data-tooltip="Reservar no Booking">
            <a href="https://www.booking.com/" target="_blank" rel="noopener noreferrer" class="float-btn float-booking" aria-label="Reservar no Booking">
                <span class="float-ping"></span>
                <span class="float-glow"></span>
                <svg viewBox="0 0 24 24" fill="currentColor" class="float-icon">
                    <path d="M6 2a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V4a2 2 0 00-2-2H6zm0 2h12v14H6V4zm2 2v2h2V6H8zm4 0v2h2V6h-2zm4 0v2h2V6h-2zm-8 4v2h2v-2H4zm4 0v2h2v-2h-2zm4 0v2h2v-2h-2z"/>
                </svg>
            </a>
            <div class="float-tooltip">Reservar no Booking</div>
        </div>

        <!-- Airbnb -->
        <div class="float-button-wrapper" data-tooltip="Reservar no Airbnb">
            <a href="https://www.airbnb.com/" target="_blank" rel="noopener noreferrer" class="float-btn float-airbnb" aria-label="Reservar no Airbnb">
                <span class="float-ping"></span>
                <span class="float-glow"></span>
                <i class="fab fa-airbnb"></i>
            </a>
            <div class="float-tooltip">Reservar no Airbnb</div>
        </div>
    </div>

    <script>
        // Menu Mobile Toggle
        const menuToggle = document.getElementById('menuToggle');
        const navMenu = document.getElementById('navMenu');

        menuToggle.addEventListener('click', () => {
            navMenu.classList.toggle('active');
            menuToggle.classList.toggle('active');
        });

        // Fechar menu ao clicar em um link
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', () => {
                navMenu.classList.remove('active');
                menuToggle.classList.remove('active');
            });
        });

        // Scroll suave
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth' });
                }
            });
        });

        // Função para scroll para comodidades
        function scrollToComodidades() {
            document.getElementById('comodidades').scrollIntoView({ behavior: 'smooth' });
        }

        // Header Background no Scroll
        const header = document.querySelector('.header');
        window.addEventListener('scroll', () => {
            if (window.scrollY > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });

        // Floating Buttons - Tooltip Hover
        document.querySelectorAll('.float-button-wrapper').forEach(wrapper => {
            const btn = wrapper.querySelector('.float-btn');
            const tooltip = wrapper.querySelector('.float-tooltip');

            btn.addEventListener('mouseenter', () => {
                tooltip.classList.add('show');
            });

            btn.addEventListener('mouseleave', () => {
                tooltip.classList.remove('show');
            });
        });

        // Animação de aparecimento ao scroll (Intersection Observer)
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -100px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('fade-in');
                    observer.unobserve(entry.target);
                }
            });
        }, observerOptions);

        document.querySelectorAll('.comodidade-card, .explore-card, .galeria-item').forEach(el => {
            observer.observe(el);
        });
    </script>
</body>
</html>
