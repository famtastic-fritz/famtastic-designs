(function ($, Drupal) {
  Drupal.behaviors.famtasticCinematic = {
    attach: function (context, settings) {
      if (context !== document) return;

      // 1. Sundance / TV Commercial GSAP Entry Animations
      gsap.from(".extreme-header h1", {
        y: 100,
        opacity: 0,
        duration: 2,
        ease: "power4.out",
        delay: 0.5
      });

      gsap.from(".extreme-header p", {
        y: 50,
        opacity: 0,
        duration: 1.5,
        ease: "power3.out",
        delay: 1.5
      });

      gsap.from(".glass-card", {
        y: 150,
        opacity: 0,
        duration: 1.5,
        stagger: 0.2,
        ease: "expo.out",
        delay: 1
      });

      // 2. Insane Three.js WebGL Background (Sundance Cinematic Level)
      const canvas = document.getElementById('webgl-canvas');
      if (!canvas) return;

      const scene = new THREE.Scene();
      const camera = new THREE.PerspectiveCamera(75, window.innerWidth / window.innerHeight, 0.1, 1000);
      const renderer = new THREE.WebGLRenderer({ canvas: canvas, alpha: true, antialias: true });
      
      renderer.setSize(window.innerWidth, window.innerHeight);
      renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));

      // Create a massive dynamic wireframe topography
      const geometry = new THREE.PlaneGeometry(100, 100, 50, 50);
      const material = new THREE.MeshBasicMaterial({ 
        color: 0xc5ff00, 
        wireframe: true,
        transparent: true,
        opacity: 0.15
      });
      
      const plane = new THREE.Mesh(geometry, material);
      plane.rotation.x = -Math.PI / 2;
      plane.position.y = -10;
      scene.add(plane);

      // Add floating monolithic cubes (Commerce/Agency metaphor)
      const cubes = [];
      const boxGeo = new THREE.BoxGeometry(2, 2, 2);
      const boxMat = new THREE.MeshPhysicalMaterial({ 
        color: 0x00f0ff,
        metalness: 0.9,
        roughness: 0.1,
        transmission: 0.9,
        transparent: true
      });

      for(let i=0; i<15; i++) {
        const mesh = new THREE.Mesh(boxGeo, boxMat);
        mesh.position.x = (Math.random() - 0.5) * 40;
        mesh.position.y = (Math.random() - 0.5) * 40;
        mesh.position.z = (Math.random() - 0.5) * 40;
        mesh.rotation.x = Math.random() * Math.PI;
        mesh.rotation.y = Math.random() * Math.PI;
        scene.add(mesh);
        cubes.push(mesh);
      }

      const ambientLight = new THREE.AmbientLight(0xffffff, 0.5);
      scene.add(ambientLight);
      const dirLight = new THREE.DirectionalLight(0xc5ff00, 5);
      dirLight.position.set(10, 20, 10);
      scene.add(dirLight);

      camera.position.z = 20;

      // Animation Loop
      const clock = new THREE.Clock();
      function animate() {
        requestAnimationFrame(animate);
        const time = clock.getElapsedTime();

        // Animate terrain vertices
        const positionAttribute = geometry.attributes.position;
        for (let i = 0; i < positionAttribute.count; i++) {
          const x = positionAttribute.getX(i);
          const y = positionAttribute.getY(i);
          const z = Math.sin(x * 0.5 + time) * Math.cos(y * 0.5 + time) * 2;
          positionAttribute.setZ(i, z);
        }
        positionAttribute.needsUpdate = true;

        // Rotate monoliths
        cubes.forEach((cube, ndx) => {
          cube.rotation.x += 0.005;
          cube.rotation.y += 0.01;
          cube.position.y += Math.sin(time + ndx) * 0.02;
        });

        renderer.render(scene, camera);
      }
      animate();

      window.addEventListener('resize', () => {
        camera.aspect = window.innerWidth / window.innerHeight;
        camera.updateProjectionMatrix();
        renderer.setSize(window.innerWidth, window.innerHeight);
      });
    }
  };
})(jQuery, Drupal);
