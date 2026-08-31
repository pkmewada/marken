// Shared product/category data, sourced from markenworld.com.
// Loaded as a plain script (no bundler) so it can run from any static host.

const CATEGORIES = [
  {
    slug: "medical-cold-chain",
    name: "Medical Cold Chain",
    tagline: "Precision cold storage for vaccines, plasma and biological samples",
    image: "images/banners/cold-storage.webp",
  },
  {
    slug: "blood-bank-equipment",
    name: "Blood Bank Equipment",
    tagline: "Instruments engineered for the safe collection and processing of blood",
    image: "images/banners/blood-bank-range.webp",
  },
  {
    slug: "hospital-furniture",
    name: "Hospital Furniture",
    tagline: "Beds, trolleys and furniture built for critical care environments",
    image: "images/banners/hospital-furniture-range.webp",
  },
  {
    slug: "medical-equipment",
    name: "Medical Equipment",
    tagline: "Monitoring, respiratory and life-support systems for every ward",
    image: "images/banners/flagship-products.webp",
  },
];

// Products without a full description/features list show a placeholder —
// MarkEn's technical specifications are not fabricated for products where
// the source page did not expose them.
const PRODUCTS = [
  // ---------------------------------------------------------------- Medical Cold Chain
  {
    slug: "ult-deep-freezer-86",
    name: "ULT Deep Freezer –86°C",
    category: "medical-cold-chain",
    image: "images/products/ult-deep-freezer-86.webp",
    featured: true,
    description:
      "Ultra Low Temperature (–86°C) Deep Freezer is engineered for the critical preservation of high-value biological specimens, including vaccines, plasma, and cell cultures. Its precision-controlled refrigeration, robust insulation, and advanced safety systems ensure ultra-stable temperatures—even under extreme ambient conditions—making it a trusted solution in biomedical research, pharmaceutical, and clinical applications.",
    features: [
      { title: "ULT Stability (–50°C to –86°C)", description: "Ensures safe long-term storage for sensitive biological materials." },
      { title: "Advanced Refrigeration System", description: "High efficiency, CFC-free compression cycle for rapid cooling and sustainability." },
      { title: "Triple Seal Door Design", description: "Robust construction with multi-point sealing for superior insulation and temperature recovery." },
      { title: "Smart Control Interface", description: "Touchscreen programming with secure access and multi-level alarm system." },
      { title: "Optional Power Backup Support", description: "Enables uninterrupted operation during power outages to protect stored samples." },
      { title: "Comprehensive Safety Mechanisms", description: "Includes fire resistance, pressure control, and thermal overload protection." },
    ],
    applications: ["Biomedical research", "Pharmaceutical storage", "Vaccine & plasma banking", "Clinical laboratories"],
  },
  { slug: "deep-freezer-40", name: "Deep Freezer –40°C", category: "medical-cold-chain", image: "images/products/deep-freezer-40.webp" },
  { slug: "laboratory-refrigerator", name: "Laboratory Refrigerator", category: "medical-cold-chain", image: "images/products/laboratory-refrigerator.webp" },
  { slug: "ice-lined-refrigerator", name: "Ice Lined Refrigerator (Vertical)", category: "medical-cold-chain", image: "images/products/ice-lined-refrigerator.webp" },
  { slug: "passive-cold-box", name: "Passive Cold Box", category: "medical-cold-chain", image: "images/products/passive-cold-box.webp" },
  { slug: "solar-walk-in-cooler", name: "Solar Walk-in Cooler and Freezer", category: "medical-cold-chain", image: "images/products/solar-walk-in-cooler.webp" },

  // ---------------------------------------------------------------- Blood Bank Equipment
  {
    slug: "refrigerated-centrifuge",
    name: "Refrigerated Centrifuge",
    category: "blood-bank-equipment",
    image: "images/products/refrigerated-centrifuge.webp",
    featured: true,
    description:
      "Our Refrigerated Centrifuge is designed for precise separation of temperature-sensitive samples in clinical, biomedical, pharmaceutical, and research laboratories. Equipped with advanced temperature control, high RPM capability, and intelligent safety features, this laboratory refrigerated centrifuge ensures reliable performance in tropical and continuous-use environments. Ideal for blood banks, pathology labs, molecular biology, and biotech applications.",
    features: [
      { title: "Advanced Rotor Recognition System", description: "Prevents overspeeding and rotor mismatch for enhanced operational safety." },
      { title: "Precision Cooling Mechanism", description: "Maintains required temperatures for sensitive biological samples during high-speed runs." },
      { title: "Microprocessor-Controlled Interface", description: "Intuitive digital control panel with programmable settings and diagnostic feedback." },
      { title: "Ultra-Quiet Operation", description: "Low-noise and low-vibration design preserves sample quality and ensures lab comfort." },
      { title: "Integrated Safety Sensors", description: "Features imbalance detection, automatic braking, and manual lid release during emergencies." },
      { title: "Built for Longevity", description: "Durable construction rated for 10+ years of use, with assured spare parts availability." },
    ],
    applications: ["Blood banks", "Pathology labs", "Molecular biology", "Biotech research"],
  },
  { slug: "contact-shock-freezer", name: "Contact Shock Freezer", category: "blood-bank-equipment", image: "images/products/contact-shock-freezer.webp" },
  { slug: "blood-bank-refrigerator", name: "Blood Bank Refrigerator", category: "blood-bank-equipment", image: "images/products/blood-bank-refrigerator.webp" },
  { slug: "blood-collection-monitor", name: "Blood Collection Monitor", category: "blood-bank-equipment", image: "images/products/blood-collection-monitor.webp" },
  { slug: "blood-donor-couch", name: "Blood Donor Couch", category: "blood-bank-equipment", image: "images/products/blood-donor-couch.webp" },
  { slug: "platelet-incubator", name: "Platelet Incubator & Agitator", category: "blood-bank-equipment", image: "images/products/platelet-incubator.webp" },

  // ---------------------------------------------------------------- Hospital Furniture
  {
    slug: "icu-7-function-bed",
    name: "ICU 7 Function Motorized Bed",
    category: "hospital-furniture",
    image: "images/products/icu-7-function-bed.webp",
    featured: true,
    description:
      "ICU 7 Function Motorized Bed is a state of the art motorized hospital bed, designed to enhance patient comfort, safety, and caregiver efficiency in intensive care environments. With precision motorized functions, aesthetic ergonomics, and a durable build, it delivers a seamless blend of design and critical performance that sets it apart in today's modern medical care settings.",
    features: [
      { title: "7 Function Electric Adjustments", description: "Offers smooth motorized control of backrest, knee-rest, height, Trendelenburg & reverse Trendelenburg positions via handset." },
      { title: "4 Section CRCA Platform", description: "Robust and corrosion-resistant Cold Rolled Close Annealed frame ensures long-term reliability and hygienic use." },
      { title: "Aesthetic Polymer Molded Panels", description: "Detachable head & foot boards crafted for ergonomic appeal and easy patient access." },
      { title: "Split ABS Side Rails", description: "Four-piece collapsible safety rails provide secure enclosure and fall prevention with effortless handling." },
      { title: "Advanced Mobility & Safety", description: "Fitted with 125mm castors and a diagonal locking system for smooth transport and firm stability." },
      { title: "Integrated Utility Enhancements", description: "Features saline stand provision, corner bumpers, and mattress retainers for enhanced critical care functionality." },
    ],
    applications: ["Intensive care units", "Post-operative recovery", "Emergency wards"],
  },
  { slug: "icu-5-function-bed", name: "ICU 5 Function Manual Deluxe Bed", category: "hospital-furniture", image: "images/products/icu-5-function-bed.webp" },
  { slug: "stretcher-trolley-ss", name: "Stretcher Trolley SS", category: "hospital-furniture", image: "images/products/stretcher-trolley-ss.webp" },
  { slug: "examination-couch", name: "Examination Couch", category: "hospital-furniture", image: "images/products/examination-couch.webp" },
  { slug: "iv-stand-ss", name: "IV Stand SS", category: "hospital-furniture", image: "images/products/iv-stand-ss.webp" },
  { slug: "bedside-locker-deluxe", name: "Bedside Locker Deluxe", category: "hospital-furniture", image: "images/products/bedside-locker-deluxe.webp" },

  // ---------------------------------------------------------------- Medical Equipment
  {
    slug: "ventilator",
    name: "Ventilator",
    category: "medical-equipment",
    image: "images/products/ventilator.webp",
    featured: true,
    description:
      "Ventilator is a cutting-edge respiratory support system engineered for high-acuity environments such as ICUs, emergency units, and surgical theaters. Designed to deliver both invasive and non-invasive ventilation, it ensures accurate oxygen delivery and carbon dioxide removal for patients in respiratory distress. With intelligent modes, intuitive controls, and robust safety systems, this ventilator stands out for its clinical reliability and patient-centric design.",
    features: [
      { title: "Multi-Mode Ventilation Support", description: "Offers invasive and non-invasive ventilation with volume and pressure control for personalized treatment." },
      { title: "High-Resolution Display Interface", description: "User-friendly touchscreen interface displays real-time waveforms, respiratory parameters, and system alerts." },
      { title: "Precise Respiratory Control", description: "Adjustable settings for tidal volume, respiratory rate, PEEP, FiO₂, and inspiratory/expiratory ratio." },
      { title: "Integrated Alarm & Safety Systems", description: "Intelligent alarms for apnea, disconnection, high pressure, and low volume ensure safe patient management." },
      { title: "Battery Backup & Portability", description: "Long-lasting battery for uninterrupted operation during transport or power loss." },
      { title: "Compact & Mobile Design", description: "Mounted on smooth-rolling castors with a lock system, ideal for bedside mobility in intensive care settings." },
    ],
    applications: ["ICUs", "Emergency units", "Surgical theaters", "Patient transport"],
  },
  { slug: "patient-monitor", name: "Patient Monitor – ME-2000", category: "medical-equipment", image: "images/products/patient-monitor.webp" },
  { slug: "radiant-warmer", name: "Radiant Warmer", category: "medical-equipment", image: "images/products/radiant-warmer.webp" },
  { slug: "syringe-pump", name: "Syringe Pump", category: "medical-equipment", image: "images/products/syringe-pump.webp" },
  { slug: "bipap", name: "BIPAP", category: "medical-equipment", image: "images/products/bipap.webp" },
  { slug: "defibrillator", name: "Defibrillator", category: "medical-equipment", image: "images/products/defibrillator.webp" },
];

function getCategory(slug) {
  return CATEGORIES.find((c) => c.slug === slug);
}
function getProduct(slug) {
  return PRODUCTS.find((p) => p.slug === slug);
}
function getProductsByCategory(slug) {
  return PRODUCTS.filter((p) => p.category === slug);
}
function getRelatedProducts(product, limit = 4) {
  return PRODUCTS.filter((p) => p.category === product.category && p.slug !== product.slug).slice(0, limit);
}
