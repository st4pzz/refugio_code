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
                <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3657.234567890123!2d-47.314756!3d-22.125434!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x94b9e5c5c5c5c5c5%3A0x5c5c5c5c5c5c5c5c!2sAnal%C3%A2ndia%2C%20SP!5e0!3m2!1spt-BR!2sbr!4v1234567890" width="100%" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
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
                        <p>+55 (14) 9999-9999</p>
                    </div>
                </div>
                <div class="info-item">
                    <i class="fas fa-envelope"></i>
                    <div>
                        <h4>Email</h4>
                        <p>contato@refugiocuscuzeiro.com.br</p>
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
    <div class="floating-buttons">
        <a href="https://wa.me/5514999999999?text=Olá!%20Gostaria%20de%20saber%20mais%20sobre%20o%20Refúgio%20do%20Cuscuzeiro" target="_blank" class="float-btn whatsapp" title="Conversar no WhatsApp">
            <i class="fab fa-whatsapp"></i>
            <span class="float-label">WhatsApp</span>
        </a>
        
        <a href="https://www.booking.com/" target="_blank" class="float-btn booking" title="Reservar no Booking">
            <i class="fas fa-calendar"></i>
            <span class="float-label">Booking</span>
        </a>
        
        <a href="https://www.airbnb.com/" target="_blank" class="float-btn airbnb" title="Reservar no Airbnb">
            <i class="fab fa-airbnb"></i>
            <span class="float-label">Airbnb</span>
        </a>
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
