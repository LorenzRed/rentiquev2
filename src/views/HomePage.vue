<template>
  <ion-page
    ><ion-header
      ><ion-toolbar
        ><ion-title
          ><span class="brand">rentique<span class="brand-dot">.</span></span></ion-title
        ><ion-buttons slot="end"
          ><ion-button router-link="/profile" aria-label="Your account"
            ><ion-icon :icon="personCircleOutline" /></ion-button></ion-buttons></ion-toolbar
    ></ion-header>
    <ion-content
      ><main class="page-shell">
        <div class="intro">
          <span class="eyebrow">A little borrowed. Entirely you.</span
          ><span class="intro-note">THE FORMALWEAR EDIT</span>
        </div>
        <section class="hero">
          <div class="hero-copy">
            <div class="hero-kicker">FOR EVERY VERSION OF YOU</div>
            <h1>Great moments.<br />Unforgettable <em>style.</em></h1>
            <p>Find the perfect look for your next occasion.<br />Discover beautiful formalwear from shops near you.</p>
            <ion-button router-link="/shops"
              >Find your look <ion-icon slot="end" :icon="arrowForwardOutline"
            /></ion-button>
            <div class="hero-foot">RENT BEAUTIFULLY. MAKE MEMORIES.</div>
          </div>
          <div class="hero-image" role="img" aria-label="Elegant formalwear for special occasions">
            <span class="image-caption">THE OCCASION COLLECTION<br /><strong>Made for your moment.</strong></span>
          </div>
        </section>
        <div class="benefits">
          <span><ion-icon :icon="sparklesOutline" /> A look for every occasion</span
          ><span><ion-icon :icon="locationOutline" /> Discover local rental shops</span
          ><span><ion-icon :icon="leafOutline" /> Wear more. Own less.</span>
        </div>
        <div class="section-heading">
          <div>
            <span class="eyebrow">YOUR OCCASION, YOUR STYLE</span>
            <h2>What are you dressing for?</h2>
          </div>
          <router-link to="/shops">Explore all <span aria-hidden="true">↗</span></router-link>
        </div>
        <section class="category-grid">
          <router-link
            v-for="category in categories"
            :key="category.name"
            :to="{ path: '/products', query: { category: category.name } }"
            class="category"
            ><img :src="category.image" :alt="category.name" loading="lazy" />
            <div>
              <span>{{ category.name }}</span><span aria-hidden="true">↗</span>
            </div></router-link
          >
        </section>
        <section class="nearby">
          <div class="nearby-icon"><ion-icon :icon="mapOutline" /></div>
          <div>
            <span class="eyebrow">SOMETHING BEAUTIFUL IS CLOSER THAN YOU THINK</span>
            <h2>Your next look. Just around the corner.</h2>
            <p>Explore rental shops in your area and find a style worth celebrating.</p>
          </div>
          <ion-button fill="outline" router-link="/map"
            >Explore the map <ion-icon slot="end" :icon="arrowForwardOutline"
          /></ion-button>
        </section>
        <footer><span class="brand">rentique.</span><span>For the moments that matter.</span></footer>
      </main></ion-content
    ></ion-page
  >
</template>
<script setup lang="ts">
import { IonPage, IonHeader, IonToolbar, IonTitle, IonContent, IonButton, IonButtons, IonIcon } from '@ionic/vue';
import {
  personCircleOutline,
  arrowForwardOutline,
  sparklesOutline,
  locationOutline,
  leafOutline,
  mapOutline,
} from 'ionicons/icons';
const categories = [
  {
    name: 'Gowns',
    image: '/images/occasion.jpg',
  },
  {
    name: 'Suits',
    image: '/images/suits.jpg',
  },
  {
    name: 'Dresses',
    image: '/images/dresses.jpg',
  },
  {
    name: 'Other formalwear',
    image: '/images/formalwear.jpg',
  },
];
</script>
<style scoped>
.brand-dot {
  color: #b99b6a;
}
.intro {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin: 4px 0 24px;
}
.intro-note {
  font-size: 10px;
  letter-spacing: 2px;
  color: #9b8b95;
}
.hero {
  display: grid;
  grid-template-columns: 1.1fr 1fr;
  border-radius: 18px;
  overflow: hidden;
  background: #eee7e8;
  min-height: 470px;
}
.hero-copy {
  padding: 52px 42px;
}
.hero-kicker {
  font-size: 10px;
  letter-spacing: 2.5px;
  color: #795a6d;
  margin-bottom: 25px;
}
.hero h1 {
  color: #44243b;
  margin-bottom: 22px;
}
.hero em {
  font-weight: 400;
  color: #9c7955;
}
.hero p {
  font-size: 14px;
}
.hero-foot {
  font-size: 9px;
  letter-spacing: 2px;
  margin-top: 30px;
  color: #8b7682;
}
.hero-image {
  background:
    linear-gradient(0deg, #261a2780, transparent 65%),
    url('/images/occasion.jpg') center 36% / cover;
  display: flex;
  align-items: end;
  padding: 32px;
}
.image-caption {
  color: white;
  font-size: 9px;
  letter-spacing: 2px;
  line-height: 2.5;
}
.image-caption strong {
  font-family: Georgia, serif;
  font-size: 24px;
  letter-spacing: 0;
  font-weight: 400;
}
.benefits {
  display: flex;
  justify-content: space-around;
  gap: 20px;
  padding: 24px 0;
  border-bottom: 1px solid #e8e0e5;
  font-size: 12px;
  color: #796876;
}
.benefits span {
  display: flex;
  align-items: center;
  gap: 9px;
}
.benefits ion-icon {
  color: #a98b56;
  font-size: 18px;
}
.section-heading h2 {
  margin: 8px 0 0;
}
.section-heading a {
  font-size: 12px;
  text-decoration: none;
  white-space: nowrap;
}
.category-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 18px;
}
.category {
  text-decoration: none;
  color: #382d35;
}
.category img {
  width: 100%;
  height: 240px;
  object-fit: cover;
  border-radius: 12px;
  background: #e8e0e5;
}
.category div {
  display: flex;
  justify-content: space-between;
  padding: 13px 2px;
  font-family: Georgia, serif;
  font-size: 20px;
}
.nearby {
  margin-top: 38px;
  padding: 30px;
  background: #f0ebdf;
  border-radius: 14px;
  display: flex;
  align-items: center;
  gap: 24px;
}
.nearby-icon {
  width: 65px;
  height: 65px;
  border-radius: 50%;
  background: #e5ddcc;
  display: grid;
  place-items: center;
  flex-shrink: 0;
  font-size: 28px;
  color: #8f774c;
}
.nearby h2 {
  font-size: 25px;
  margin: 10px 0;
}
.nearby p {
  font-size: 12px;
  margin-bottom: 0;
}
.nearby ion-button {
  margin-left: auto;
  flex-shrink: 0;
}
footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 42px;
  color: #8f8089;
  font-size: 12px;
}
footer .brand {
  font-size: 22px;
}
@media (max-width: 700px) {
  .intro-note {
    display: none;
  }
  .hero {
    grid-template-columns: 1fr 0.7fr;
    min-height: 385px;
  }
  .hero-copy {
    padding: 32px 20px;
  }
  .hero h1 {
    font-size: 37px;
  }
  .hero p {
    font-size: 12px;
  }
  .hero-image {
    padding: 14px;
  }
  .image-caption strong {
    font-size: 17px;
  }
  .benefits {
    font-size: 10px;
    gap: 10px;
  }
  .benefits span {
    gap: 4px;
  }
  .category-grid {
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
  }
  .category img {
    height: 200px;
  }
  .nearby {
    flex-wrap: wrap;
    padding: 24px;
  }
  .nearby-icon {
    display: none;
  }
  .nearby ion-button {
    margin-left: 0;
  }
  .nearby .eyebrow {
    font-size: 9px;
  }
}
@media (max-width: 480px) {
  .hero {
    grid-template-columns: 1fr;
  }
  .hero-image {
    min-height: 220px;
    padding: 24px;
  }
  .hero h1 {
    font-size: 40px;
  }
  .hero-copy {
    padding: 30px 24px;
  }
  .benefits {
    flex-wrap: wrap;
  }
}
</style>
