import data from "./data.js";

new Vue({
  el: "#element", // 指定Vue实例挂载的DOM元素的ID，Vue实例将挂载到页面上ID为element的元素上。
  data: {
    element: data, // 网站文案数据
    projectDialog: false, // 项目演示窗口打开
    projectIndex: 0, // 当前展示项目索引
  },
  mounted() {
    // 打字开启
    this.startTyping();
    // 动画加载
    this.animationLoad();
  },
  methods: {
    // 打字开启
    startTyping() {
      new Typed(".element1Typet", {
        strings: this.element.index.me,
        typeSpeed: 30,
        backDelay: 2000,
        backSpeed: 50,
        loop: true,
      });
    },
    // 平滑跳转
    scrollGoTo(text) {
      window.scrollTo({
        top: document.querySelector(`.${text}`).offsetTop,
        behavior: "smooth",
      });
    },
    // 动画加载
    animationLoad() {
      let animationDom = [
        [".contentTitle", 0],
        [".contentSubTitle", 500],
        [".contentContact", 1000],
        [".element1LoadMore", 1500],
        [".element2ContentTitle", 0],
        [".element2ContentMe", 500],
        [".element2ContentText li", 500],
        [".element2LoadMore", 1000],
        [".element3ContentBox", 500],
        [".element4Content li", 500],
      ];
      let a = ScrollReveal();
      animationDom.forEach((item) => {
        a.reveal(item[0], {
          duration: 1500,
          delay: 100,
          origin: "bottom",
          mobile: true,
          distance: "2rem",
          opacity: 0.001,
          easing: "cubic-bezier(.98,0,.04,1)",
        });
      });
    },

    // 项目展示
    showProject(i) {
      this.projectIndex = i;
      this.projectDialog = true;
      setTimeout(() => {
        let dom = document.querySelector(".el-dialog__body ul");
        if (!dom) {
          return;
        }
        let Viewer = window.Viewer;
        new Viewer(dom);
      }, 0);
    },
  },
});
